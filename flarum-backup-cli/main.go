package main

import (
	"archive/tar"
	"compress/gzip"
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"time"
)

const (
	appName          = "flarum-backup"
	manifestName     = "manifest.json"
	databaseName     = "database.sql"
	filesPrefix      = "files/"
	formatVersion    = 1
	defaultDataDir   = "/app"
	defaultPersist   = "/persist"
	defaultAssets    = "/app/public/assets"
	defaultStorage   = "/app/storage"
	defaultDumpBin   = "mariadb-dump"
	defaultClientBin = "mariadb"
	maxArchiveEntry  = 2 << 30
	dirPerm          = 0o750
	filePerm         = 0o640
)

var (
	version        = "dev"
	commit         = "none"
	commandContext = exec.CommandContext
)

type manifest struct {
	FormatVersion int       `json:"format_version"`
	CreatedAt     time.Time `json:"created_at"`
	AppVersion    string    `json:"app_version"`
	Hostname      string    `json:"hostname,omitempty"`
	Database      bool      `json:"database"`
	Files         bool      `json:"files"`
	Paths         pathInfo  `json:"paths"`
}

type pathInfo struct {
	DataDir    string `json:"data_dir"`
	PersistDir string `json:"persist_dir"`
	AssetsDir  string `json:"assets_dir"`
	StorageDir string `json:"storage_dir"`
}

type dbConfig struct {
	Host      string
	Port      string
	Name      string
	User      string
	Password  string
	DumpBin   string
	ClientBin string
}

type paths struct {
	DataDir    string
	PersistDir string
	AssetsDir  string
	StorageDir string
}

func main() {
	if err := run(os.Args[1:]); err != nil {
		fmt.Fprintf(os.Stderr, "%s: %v\n", appName, err)
		os.Exit(1)
	}
}

func run(args []string) error {
	if len(args) < 1 {
		printUsage(os.Stderr)
		return errors.New("command required (export|restore|version|help)")
	}

	switch args[0] {
	case "export":
		return cmdExport(args[1:])
	case "restore":
		return cmdRestore(args[1:])
	case "version", "-version", "--version":
		fmt.Printf("%s %s (%s)\n", appName, version, commit)
		return nil
	case "help", "-h", "--help":
		printUsage(os.Stdout)
		return nil
	default:
		printUsage(os.Stderr)
		return fmt.Errorf("unknown command %q", args[0])
	}
}

func printUsage(w io.Writer) {
	fmt.Fprintf(w, `Usage:
  %s export [flags]
  %s restore [flags]
  %s version

Export creates a gzipped tar with database dump and Flarum files.
Restore applies an archive created by export.

Environment (used as flag defaults):
  DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
  FLARUM_HOME FLARUM_PERSIST_DIR FLARUM_ASSETS_DIR FLARUM_STORAGE_DIR
  FLARUM_BACKUP_DUMP_BIN FLARUM_BACKUP_CLIENT_BIN

Examples:
  %s export -o /backups/forum.tar.gz
  %s restore -i /backups/forum.tar.gz --force
  %s export --skip-db -o files-only.tar.gz
`, appName, appName, appName, appName, appName, appName)
}

func cmdExport(args []string) error {
	fs := flag.NewFlagSet("export", flag.ContinueOnError)
	fs.SetOutput(io.Discard)

	out := fs.String("o", "", "output archive path (default: flarum-backup-<timestamp>.tar.gz)")
	outputLong := fs.String("output", "", "output archive path")
	skipDB := fs.Bool("skip-db", false, "omit database dump")
	skipFiles := fs.Bool("skip-files", false, "omit files")
	dataDir := fs.String("data-dir", envOr("FLARUM_HOME", defaultDataDir), "Flarum app directory")
	persistDir := fs.String("persist-dir", envOr("FLARUM_PERSIST_DIR", defaultPersist), "persist directory (config.php)")
	assetsDir := fs.String("assets-dir", envOr("FLARUM_ASSETS_DIR", defaultAssets), "assets directory")
	storageDir := fs.String("storage-dir", envOr("FLARUM_STORAGE_DIR", defaultStorage), "storage directory")

	if err := fs.Parse(args); err != nil {
		return err
	}

	outPath := firstNonEmpty(*outputLong, *out)
	if outPath == "" {
		outPath = fmt.Sprintf("flarum-backup-%s.tar.gz", time.Now().UTC().Format("20060102-150405"))
	}

	if *skipDB && *skipFiles {
		return errors.New("nothing to export: both --skip-db and --skip-files set")
	}

	p := paths{
		DataDir:    *dataDir,
		PersistDir: *persistDir,
		AssetsDir:  *assetsDir,
		StorageDir: *storageDir,
	}

	db := loadDBConfig()
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Hour)
	defer cancel()

	return exportArchive(ctx, outPath, p, db, !*skipDB, !*skipFiles)
}

func cmdRestore(args []string) error {
	fs := flag.NewFlagSet("restore", flag.ContinueOnError)
	fs.SetOutput(io.Discard)

	in := fs.String("i", "", "input archive path")
	inputLong := fs.String("input", "", "input archive path")
	force := fs.Bool("force", false, "overwrite existing files")
	skipDB := fs.Bool("skip-db", false, "omit database restore")
	skipFiles := fs.Bool("skip-files", false, "omit file restore")
	dataDir := fs.String("data-dir", envOr("FLARUM_HOME", defaultDataDir), "Flarum app directory")
	persistDir := fs.String("persist-dir", envOr("FLARUM_PERSIST_DIR", defaultPersist), "persist directory (config.php)")
	assetsDir := fs.String("assets-dir", envOr("FLARUM_ASSETS_DIR", defaultAssets), "assets directory")
	storageDir := fs.String("storage-dir", envOr("FLARUM_STORAGE_DIR", defaultStorage), "storage directory")

	if err := fs.Parse(args); err != nil {
		return err
	}

	inPath := firstNonEmpty(*inputLong, *in)
	if inPath == "" {
		return errors.New("restore requires -i/--input")
	}
	if !*force {
		return errors.New("restore refuses to run without --force")
	}
	if *skipDB && *skipFiles {
		return errors.New("nothing to restore: both --skip-db and --skip-files set")
	}

	p := paths{
		DataDir:    *dataDir,
		PersistDir: *persistDir,
		AssetsDir:  *assetsDir,
		StorageDir: *storageDir,
	}

	db := loadDBConfig()
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Hour)
	defer cancel()

	return restoreArchive(ctx, inPath, p, db, !*skipDB, !*skipFiles)
}

func loadDBConfig() dbConfig {
	return dbConfig{
		Host:      envOr("DB_HOST", "mariadb"),
		Port:      envOr("DB_PORT", "3306"),
		Name:      envOr("DB_DATABASE", "flarum"),
		User:      envOr("DB_USERNAME", "flarum"),
		Password:  os.Getenv("DB_PASSWORD"),
		DumpBin:   envOr("FLARUM_BACKUP_DUMP_BIN", defaultDumpBin),
		ClientBin: envOr("FLARUM_BACKUP_CLIENT_BIN", defaultClientBin),
	}
}

func exportArchive(ctx context.Context, outPath string, p paths, db dbConfig, withDB, withFiles bool) error {
	if err := os.MkdirAll(filepath.Dir(outPath), 0o750); err != nil && filepath.Dir(outPath) != "." {
		return fmt.Errorf("create output dir: %w", err)
	}

	tmp, err := os.CreateTemp(filepath.Dir(outPath), ".flarum-backup-*.tmp")
	if err != nil {
		return fmt.Errorf("create temp archive: %w", err)
	}
	tmpName := tmp.Name()
	defer func() {
		_ = os.Remove(tmpName)
	}()

	gz := gzip.NewWriter(tmp)
	tw := tar.NewWriter(gz)

	host, _ := os.Hostname()
	man := manifest{
		FormatVersion: formatVersion,
		CreatedAt:     time.Now().UTC(),
		AppVersion:    version,
		Hostname:      host,
		Database:      withDB,
		Files:         withFiles,
		Paths: pathInfo{
			DataDir:    p.DataDir,
			PersistDir: p.PersistDir,
			AssetsDir:  p.AssetsDir,
			StorageDir: p.StorageDir,
		},
	}

	if err := writeJSONTar(tw, manifestName, man); err != nil {
		_ = tw.Close()
		_ = gz.Close()
		_ = tmp.Close()
		return err
	}

	if withDB {
		fmt.Fprintf(os.Stderr, "dumping database %s@%s:%s/%s\n", db.User, db.Host, db.Port, db.Name)
		if err := writeDatabaseDump(ctx, tw, db); err != nil {
			_ = tw.Close()
			_ = gz.Close()
			_ = tmp.Close()
			return err
		}
	}

	if withFiles {
		fmt.Fprintln(os.Stderr, "archiving files")
		if err := writeFiles(tw, p); err != nil {
			_ = tw.Close()
			_ = gz.Close()
			_ = tmp.Close()
			return err
		}
	}

	if err := tw.Close(); err != nil {
		_ = gz.Close()
		_ = tmp.Close()
		return fmt.Errorf("close tar: %w", err)
	}
	if err := gz.Close(); err != nil {
		_ = tmp.Close()
		return fmt.Errorf("close gzip: %w", err)
	}
	if err := tmp.Close(); err != nil {
		return fmt.Errorf("close temp file: %w", err)
	}

	if err := os.Rename(tmpName, outPath); err != nil {
		return fmt.Errorf("finalize archive: %w", err)
	}

	fmt.Fprintf(os.Stderr, "wrote %s\n", outPath)
	return nil
}

func writeDatabaseDump(ctx context.Context, tw *tar.Writer, db dbConfig) error {
	if err := validateBinName(db.DumpBin); err != nil {
		return err
	}
	if db.Password == "" {
		return errors.New("DB_PASSWORD is required for database export")
	}

	args := []string{
		"-h", db.Host,
		"-P", db.Port,
		"-u", db.User,
		"--single-transaction",
		"--routines",
		"--triggers",
		"--databases", db.Name,
	}

	cmd := commandContext(ctx, db.DumpBin, args...)
	cmd.Env = append(os.Environ(), "MYSQL_PWD="+db.Password)
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	cmd.Stderr = os.Stderr

	if err := cmd.Start(); err != nil {
		return fmt.Errorf("start %s: %w", db.DumpBin, err)
	}

	pr, pw := io.Pipe()
	errCh := make(chan error, 1)
	go func() {
		_, copyErr := io.Copy(pw, stdout)
		_ = pw.CloseWithError(copyErr)
		errCh <- copyErr
	}()

	if err := writeStreamTar(tw, databaseName, pr); err != nil {
		_ = cmd.Process.Kill()
		_ = pr.Close()
		_ = cmd.Wait()
		return err
	}
	if copyErr := <-errCh; copyErr != nil {
		_ = cmd.Wait()
		return fmt.Errorf("read dump output: %w", copyErr)
	}
	if err := cmd.Wait(); err != nil {
		return fmt.Errorf("%s failed: %w", db.DumpBin, err)
	}
	return nil
}

func writeFiles(tw *tar.Writer, p paths) error {
	configPath := filepath.Join(p.PersistDir, "config.php")
	if st, err := os.Stat(configPath); err == nil && st.Mode().IsRegular() {
		if err := addFile(tw, filesPrefix+"persist/config.php", configPath); err != nil {
			return err
		}
	} else if alt := filepath.Join(p.DataDir, "config.php"); fileExists(alt) {
		if err := addFile(tw, filesPrefix+"persist/config.php", alt); err != nil {
			return err
		}
	}

	if err := addTree(tw, filesPrefix+"assets", p.AssetsDir, nil); err != nil {
		return fmt.Errorf("assets: %w", err)
	}

	skipStorage := map[string]struct{}{
		"cache":     {},
		"sessions":  {},
		"logs":      {},
		"tmp":       {},
		"views":     {},
		"less":      {},
		"formatter": {},
		"locale":    {},
	}
	if err := addTree(tw, filesPrefix+"storage", p.StorageDir, skipStorage); err != nil {
		return fmt.Errorf("storage: %w", err)
	}
	return nil
}

func restoreArchive(ctx context.Context, inPath string, p paths, db dbConfig, withDB, withFiles bool) error {
	f, err := openUserFile(inPath)
	if err != nil {
		return fmt.Errorf("open archive: %w", err)
	}
	defer f.Close()

	gz, err := gzip.NewReader(f)
	if err != nil {
		return fmt.Errorf("gzip: %w", err)
	}
	defer gz.Close()

	tr := tar.NewReader(gz)
	var man *manifest
	var dbPath string
	tmpDir, err := os.MkdirTemp("", "flarum-restore-*")
	if err != nil {
		return err
	}
	defer os.RemoveAll(tmpDir)

	for {
		hdr, err := tr.Next()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return fmt.Errorf("read tar: %w", err)
		}

		name := filepath.ToSlash(hdr.Name)
		if err := validateArchivePath(name); err != nil {
			return err
		}
		name = filepath.Clean(name)
		if name == "." {
			continue
		}

		switch {
		case name == manifestName:
			man, err = decodeManifest(io.LimitReader(tr, maxArchiveEntry))
			if err != nil {
				return err
			}
		case name == databaseName:
			dbPath = filepath.Join(tmpDir, databaseName)
			if err := writeFileFromTar(tmpDir, databaseName, io.LimitReader(tr, maxArchiveEntry), hdr.FileInfo().Mode()); err != nil {
				return err
			}
		case strings.HasPrefix(name, filesPrefix):
			if !withFiles {
				if err := discardLimited(tr); err != nil {
					return err
				}
				continue
			}
			rel := strings.TrimPrefix(name, filesPrefix)
			rootDir, relPath, err := mapRestoreTarget(rel, p)
			if err != nil {
				return err
			}
			if err := restoreTarEntry(rootDir, relPath, hdr, io.LimitReader(tr, maxArchiveEntry)); err != nil {
				return err
			}
		default:
			if err := discardLimited(tr); err != nil {
				return err
			}
		}
	}

	if man == nil {
		return errors.New("archive missing manifest.json")
	}
	if man.FormatVersion > formatVersion {
		return fmt.Errorf("unsupported archive format version %d", man.FormatVersion)
	}

	if withDB && man.Database {
		if dbPath == "" {
			return errors.New("archive claims database but database.sql is missing")
		}
		fmt.Fprintf(os.Stderr, "restoring database %s@%s:%s/%s\n", db.User, db.Host, db.Port, db.Name)
		if err := restoreDatabase(ctx, db, dbPath); err != nil {
			return err
		}
	} else if withDB && !man.Database {
		fmt.Fprintln(os.Stderr, "archive has no database dump, skipping db")
	}

	fmt.Fprintf(os.Stderr, "restored from %s\n", inPath)
	return nil
}

func mapRestoreTarget(rel string, p paths) (rootDir, relPath string, err error) {
	rel = filepath.Clean(rel)
	switch {
	case rel == "persist/config.php":
		return p.PersistDir, "config.php", nil
	case rel == "assets":
		return p.AssetsDir, ".", nil
	case strings.HasPrefix(rel, "assets"+string(os.PathSeparator)):
		return p.AssetsDir, strings.TrimPrefix(rel, "assets"+string(os.PathSeparator)), nil
	case rel == "storage":
		return p.StorageDir, ".", nil
	case strings.HasPrefix(rel, "storage"+string(os.PathSeparator)):
		return p.StorageDir, strings.TrimPrefix(rel, "storage"+string(os.PathSeparator)), nil
	default:
		return "", "", fmt.Errorf("unknown archive file path %q", rel)
	}
}

func mapRestorePath(rel string, p paths) (string, error) {
	rootDir, relPath, err := mapRestoreTarget(rel, p)
	if err != nil {
		return "", err
	}
	if relPath == "." {
		return rootDir, nil
	}
	return filepath.Join(rootDir, relPath), nil
}

func restoreDatabase(ctx context.Context, db dbConfig, sqlPath string) error {
	if err := validateBinName(db.ClientBin); err != nil {
		return err
	}
	if db.Password == "" {
		return errors.New("DB_PASSWORD is required for database restore")
	}

	sqlFile, err := openUserFile(sqlPath)
	if err != nil {
		return err
	}
	defer sqlFile.Close()

	args := []string{
		"-h", db.Host,
		"-P", db.Port,
		"-u", db.User,
	}
	cmd := commandContext(ctx, db.ClientBin, args...)
	cmd.Env = append(os.Environ(), "MYSQL_PWD="+db.Password)
	cmd.Stdin = sqlFile
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("%s restore failed: %w", db.ClientBin, err)
	}
	return nil
}

func writeJSONTar(tw *tar.Writer, name string, v any) error {
	buf, err := json.MarshalIndent(v, "", "  ")
	if err != nil {
		return err
	}
	buf = append(buf, '\n')
	hdr := &tar.Header{
		Name:    name,
		Mode:    0o644,
		Size:    int64(len(buf)),
		ModTime: time.Now().UTC(),
	}
	if err := tw.WriteHeader(hdr); err != nil {
		return err
	}
	_, err = tw.Write(buf)
	return err
}

func writeStreamTar(tw *tar.Writer, name string, r io.Reader) error {
	tmp, err := os.CreateTemp("", "flarum-dump-*.sql")
	if err != nil {
		return err
	}
	tmpName := tmp.Name()
	defer os.Remove(tmpName)

	n, err := io.Copy(tmp, io.LimitReader(r, maxArchiveEntry))
	if err != nil {
		_ = tmp.Close()
		return err
	}
	if err := tmp.Close(); err != nil {
		return err
	}

	f, err := openUserFile(tmpName)
	if err != nil {
		return err
	}
	defer f.Close()

	hdr := &tar.Header{
		Name:    name,
		Mode:    0o600,
		Size:    n,
		ModTime: time.Now().UTC(),
	}
	if err := tw.WriteHeader(hdr); err != nil {
		return err
	}
	_, err = io.Copy(tw, f)
	return err
}

func addFile(tw *tar.Writer, name, src string) error {
	f, err := openUserFile(src)
	if err != nil {
		return err
	}
	defer f.Close()

	st, err := f.Stat()
	if err != nil {
		return err
	}
	hdr, err := tar.FileInfoHeader(st, "")
	if err != nil {
		return err
	}
	hdr.Name = name
	if err := tw.WriteHeader(hdr); err != nil {
		return err
	}
	_, err = io.Copy(tw, io.LimitReader(f, maxArchiveEntry))
	return err
}

func addTree(tw *tar.Writer, prefix, root string, skipTop map[string]struct{}) error {
	st, err := os.Stat(root)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			return nil
		}
		return err
	}
	if !st.IsDir() {
		return fmt.Errorf("%s is not a directory", root)
	}

	return filepath.WalkDir(root, func(path string, d fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		rel, err := filepath.Rel(root, path)
		if err != nil {
			return err
		}
		if rel == "." {
			hdr := &tar.Header{
				Name:     prefix + "/",
				Typeflag: tar.TypeDir,
				Mode:     int64(dirPerm),
				ModTime:  time.Now().UTC(),
			}
			return tw.WriteHeader(hdr)
		}

		top, _, _ := strings.Cut(rel, string(os.PathSeparator))
		if skipTop != nil {
			if _, skip := skipTop[top]; skip {
				if d.IsDir() && top == rel {
					return fs.SkipDir
				}
				if top == rel {
					return nil
				}
			}
		}

		info, err := d.Info()
		if err != nil {
			return err
		}

		name := filepath.ToSlash(filepath.Join(prefix, rel))
		if d.IsDir() {
			hdr, err := tar.FileInfoHeader(info, "")
			if err != nil {
				return err
			}
			hdr.Name = name + "/"
			return tw.WriteHeader(hdr)
		}
		if !info.Mode().IsRegular() {
			return nil
		}
		return addFile(tw, name, path)
	})
}

func decodeManifest(r io.Reader) (*manifest, error) {
	var man manifest
	dec := json.NewDecoder(r)
	if err := dec.Decode(&man); err != nil {
		return nil, fmt.Errorf("manifest: %w", err)
	}
	return &man, nil
}

func restoreTarEntry(rootDir, relPath string, hdr *tar.Header, r io.Reader) error {
	if err := os.MkdirAll(rootDir, dirPerm); err != nil {
		return err
	}
	root, err := os.OpenRoot(rootDir)
	if err != nil {
		return err
	}
	defer root.Close()

	switch hdr.Typeflag {
	case tar.TypeDir:
		if relPath == "." {
			return nil
		}
		return root.MkdirAll(relPath, dirPerm)
	case tar.TypeReg, tar.TypeRegA:
		if strings.HasSuffix(hdr.Name, "/") || relPath == "." {
			if relPath == "." {
				return nil
			}
			return root.MkdirAll(relPath, dirPerm)
		}
		if dir := filepath.Dir(relPath); dir != "." {
			if err := root.MkdirAll(dir, dirPerm); err != nil {
				return err
			}
		}
		return writeRootFile(root, relPath, r, hdr.FileInfo().Mode())
	default:
		return discardLimited(r)
	}
}

func writeFileFromTar(rootDir, relPath string, r io.Reader, mode fs.FileMode) error {
	if err := os.MkdirAll(rootDir, dirPerm); err != nil {
		return err
	}
	root, err := os.OpenRoot(rootDir)
	if err != nil {
		return err
	}
	defer root.Close()
	return writeRootFile(root, relPath, r, mode)
}

func writeRootFile(root *os.Root, relPath string, r io.Reader, mode fs.FileMode) error {
	if mode == 0 {
		mode = filePerm
	}
	f, err := root.OpenFile(relPath, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode.Perm())
	if err != nil {
		return err
	}
	defer f.Close()
	_, err = io.Copy(f, io.LimitReader(r, maxArchiveEntry))
	return err
}

func validateArchivePath(name string) error {
	if name == "" {
		return errors.New("empty archive path")
	}
	if strings.HasPrefix(name, "/") || filepath.IsAbs(name) {
		return fmt.Errorf("refusing absolute path %q", name)
	}
	for part := range strings.SplitSeq(filepath.ToSlash(name), "/") {
		if part == ".." {
			return fmt.Errorf("refusing unsafe path %q", name)
		}
	}
	return nil
}

func validateBinName(name string) error {
	if name == "" {
		return errors.New("empty command name")
	}
	clean := filepath.Clean(name)
	if strings.Contains(clean, "..") {
		return fmt.Errorf("unsafe command path %q", name)
	}
	if strings.ContainsAny(name, "\x00\n\r") {
		return fmt.Errorf("invalid command name %q", name)
	}
	return nil
}

func openUserFile(path string) (*os.File, error) {
	clean := filepath.Clean(path)
	return os.Open(clean) // #nosec G304 -- operator supplied path from CLI flags
}

func discardLimited(r io.Reader) error {
	_, err := io.Copy(io.Discard, io.LimitReader(r, maxArchiveEntry))
	return err
}

func envOr(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func firstNonEmpty(values ...string) string {
	for _, v := range values {
		if v != "" {
			return v
		}
	}
	return ""
}

func fileExists(path string) bool {
	st, err := os.Stat(path)
	return err == nil && st.Mode().IsRegular()
}

func listArchiveFiles(path string) ([]string, error) {
	f, err := openUserFile(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()
	gz, err := gzip.NewReader(f)
	if err != nil {
		return nil, err
	}
	defer gz.Close()
	tr := tar.NewReader(gz)
	var names []string
	for {
		hdr, err := tr.Next()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return nil, err
		}
		names = append(names, hdr.Name)
		if err := discardLimited(tr); err != nil {
			return nil, err
		}
	}
	sort.Strings(names)
	return names, nil
}
