package main

import (
	"archive/tar"
	"compress/gzip"
	"context"
	"encoding/json"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"testing"
	"time"
)

func TestRunHelpAndVersion(t *testing.T) {
	if err := run([]string{"help"}); err != nil {
		t.Fatalf("help: %v", err)
	}
	if err := run([]string{"version"}); err != nil {
		t.Fatalf("version: %v", err)
	}
	if err := run(nil); err == nil {
		t.Fatal("expected error for missing command")
	}
	if err := run([]string{"nope"}); err == nil {
		t.Fatal("expected error for unknown command")
	}
}

func TestExportRestoreFilesRoundTrip(t *testing.T) {
	root := t.TempDir()
	persist := filepath.Join(root, "persist")
	assets := filepath.Join(root, "assets")
	storage := filepath.Join(root, "storage")
	data := filepath.Join(root, "app")

	mustMkdir(t, persist)
	mustMkdir(t, filepath.Join(assets, "avatars"))
	mustMkdir(t, filepath.Join(storage, "cache"))
	mustMkdir(t, filepath.Join(storage, "keep"))
	mustWrite(t, filepath.Join(persist, "config.php"), "<?php return [];\n")
	mustWrite(t, filepath.Join(assets, "avatars", "a.png"), "png")
	mustWrite(t, filepath.Join(storage, "cache", "x"), "cache-should-skip")
	mustWrite(t, filepath.Join(storage, "keep", "note.txt"), "keep-me")

	out := filepath.Join(root, "backup.tar.gz")
	p := paths{DataDir: data, PersistDir: persist, AssetsDir: assets, StorageDir: storage}
	ctx := context.Background()
	if err := exportArchive(ctx, out, p, dbConfig{}, false, true); err != nil {
		t.Fatalf("export: %v", err)
	}

	names, err := listArchiveFiles(out)
	if err != nil {
		t.Fatalf("list: %v", err)
	}
	joined := strings.Join(names, "\n")
	if !strings.Contains(joined, "manifest.json") {
		t.Fatalf("missing manifest: %v", names)
	}
	if !strings.Contains(joined, "files/persist/config.php") {
		t.Fatalf("missing config: %v", names)
	}
	if !strings.Contains(joined, "files/assets/avatars/a.png") {
		t.Fatalf("missing asset: %v", names)
	}
	if !strings.Contains(joined, "files/storage/keep/note.txt") {
		t.Fatalf("missing storage file: %v", names)
	}
	if strings.Contains(joined, "files/storage/cache/") {
		t.Fatalf("cache should be skipped: %v", names)
	}

	restoreRoot := filepath.Join(root, "restore")
	rp := paths{
		DataDir:    filepath.Join(restoreRoot, "app"),
		PersistDir: filepath.Join(restoreRoot, "persist"),
		AssetsDir:  filepath.Join(restoreRoot, "assets"),
		StorageDir: filepath.Join(restoreRoot, "storage"),
	}
	mustMkdir(t, rp.PersistDir)
	mustMkdir(t, rp.AssetsDir)
	mustMkdir(t, rp.StorageDir)

	if err := restoreArchive(ctx, out, rp, dbConfig{}, false, true); err != nil {
		t.Fatalf("restore: %v", err)
	}

	assertFile(t, filepath.Join(rp.PersistDir, "config.php"), "<?php return [];\n")
	assertFile(t, filepath.Join(rp.AssetsDir, "avatars", "a.png"), "png")
	assertFile(t, filepath.Join(rp.StorageDir, "keep", "note.txt"), "keep-me")
}

func TestExportRequiresSomething(t *testing.T) {
	err := cmdExport([]string{"--skip-db", "--skip-files", "-o", filepath.Join(t.TempDir(), "x.tar.gz")})
	if err == nil {
		t.Fatal("expected error")
	}
}

func TestRestoreRequiresForceAndInput(t *testing.T) {
	if err := cmdRestore(nil); err == nil {
		t.Fatal("expected missing input error")
	}
	if err := cmdRestore([]string{"-i", "missing.tar.gz"}); err == nil {
		t.Fatal("expected --force error")
	}
}

func TestRefuseUnsafeTarPaths(t *testing.T) {
	root := t.TempDir()
	bad := filepath.Join(root, "bad.tar.gz")
	if err := writeEvilArchive(bad, "../escape.txt", "nope"); err != nil {
		t.Fatal(err)
	}
	p := paths{
		PersistDir: filepath.Join(root, "persist"),
		AssetsDir:  filepath.Join(root, "assets"),
		StorageDir: filepath.Join(root, "storage"),
	}
	mustMkdir(t, p.PersistDir)
	err := restoreArchive(context.Background(), bad, p, dbConfig{}, false, true)
	if err == nil {
		t.Fatal("expected unsafe path error")
	}
}

func TestMapRestorePath(t *testing.T) {
	p := paths{
		PersistDir: "/persist",
		AssetsDir:  "/assets",
		StorageDir: "/storage",
	}
	got, err := mapRestorePath("persist/config.php", p)
	if err != nil || got != filepath.Join("/persist", "config.php") {
		t.Fatalf("config path: %q %v", got, err)
	}
	got, err = mapRestorePath("assets/x.png", p)
	if err != nil || got != filepath.Join("/assets", "x.png") {
		t.Fatalf("assets path: %q %v", got, err)
	}
	if _, err := mapRestorePath("vendor/evil.php", p); err == nil {
		t.Fatal("expected unknown path error")
	}
}

func TestValidateBinName(t *testing.T) {
	if err := validateBinName("mariadb-dump"); err != nil {
		t.Fatal(err)
	}
	if err := validateBinName(""); err == nil {
		t.Fatal("expected empty error")
	}
	if err := validateBinName("bin/../../evil"); err == nil {
		t.Fatal("expected unsafe path error")
	}
}

func TestDatabaseExportRestoreWithStub(t *testing.T) {
	if runtime.GOOS == "windows" {
		t.Skip("shell stubs")
	}
	root := t.TempDir()
	binDir := filepath.Join(root, "bin")
	mustMkdir(t, binDir)

	dumpBin := filepath.Join(binDir, "mariadb-dump")
	clientBin := filepath.Join(binDir, "mariadb")
	mustWrite(t, dumpBin, "#!/bin/sh\necho '-- stub dump'\necho 'CREATE DATABASE stub;'\n")
	mustWrite(t, clientBin, "#!/bin/sh\ncat > \""+filepath.Join(root, "restored.sql")+"\"\n")
	if err := os.Chmod(dumpBin, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.Chmod(clientBin, 0o755); err != nil {
		t.Fatal(err)
	}

	oldPath := os.Getenv("PATH")
	t.Cleanup(func() { _ = os.Setenv("PATH", oldPath) })
	if err := os.Setenv("PATH", binDir+string(os.PathListSeparator)+oldPath); err != nil {
		t.Fatal(err)
	}

	out := filepath.Join(root, "db.tar.gz")
	p := paths{
		DataDir:    filepath.Join(root, "app"),
		PersistDir: filepath.Join(root, "persist"),
		AssetsDir:  filepath.Join(root, "assets"),
		StorageDir: filepath.Join(root, "storage"),
	}
	mustMkdir(t, p.PersistDir)
	db := dbConfig{
		Host:      "127.0.0.1",
		Port:      "3306",
		Name:      "flarum",
		User:      "flarum",
		Password:  "secret",
		DumpBin:   dumpBin,
		ClientBin: clientBin,
	}

	ctx, cancel := context.WithTimeout(context.Background(), time.Minute)
	defer cancel()
	if err := exportArchive(ctx, out, p, db, true, false); err != nil {
		t.Fatalf("export db: %v", err)
	}

	man := readManifest(t, out)
	if !man.Database || man.Files {
		t.Fatalf("unexpected manifest: %+v", man)
	}

	if err := restoreArchive(ctx, out, p, db, true, false); err != nil {
		t.Fatalf("restore db: %v", err)
	}
	data, err := os.ReadFile(filepath.Join(root, "restored.sql"))
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(data), "CREATE DATABASE stub") {
		t.Fatalf("unexpected restore input: %s", data)
	}
}

func TestCmdExportCreatesDefaultName(t *testing.T) {
	root := t.TempDir()
	cwd, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.Chdir(cwd) })

	persist := filepath.Join(root, "persist")
	mustMkdir(t, persist)
	mustWrite(t, filepath.Join(persist, "config.php"), "x")

	err = cmdExport([]string{
		"--skip-db",
		"--persist-dir", persist,
		"--assets-dir", filepath.Join(root, "missing-assets"),
		"--storage-dir", filepath.Join(root, "missing-storage"),
		"--data-dir", filepath.Join(root, "app"),
		"-o", "named.tar.gz",
	})
	if err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(filepath.Join(root, "named.tar.gz")); err != nil {
		t.Fatal(err)
	}
}

func TestCommandContextHook(t *testing.T) {
	called := false
	old := commandContext
	commandContext = func(ctx context.Context, name string, args ...string) *exec.Cmd {
		called = true
		return old(ctx, name, args...)
	}
	t.Cleanup(func() { commandContext = old })

	root := t.TempDir()
	bin := filepath.Join(root, "tool")
	mustWrite(t, bin, "#!/bin/sh\nexit 0\n")
	_ = os.Chmod(bin, 0o755)
	cmd := commandContext(context.Background(), bin)
	if err := cmd.Run(); err != nil {
		t.Fatal(err)
	}
	if !called {
		t.Fatal("hook not called")
	}
}

func writeEvilArchive(path, name, body string) error {
	f, err := os.Create(path)
	if err != nil {
		return err
	}
	defer f.Close()
	gz := gzip.NewWriter(f)
	defer gz.Close()
	tw := tar.NewWriter(gz)
	defer tw.Close()

	man := manifest{FormatVersion: formatVersion, CreatedAt: time.Now().UTC(), Files: true}
	b, _ := json.Marshal(man)
	_ = tw.WriteHeader(&tar.Header{Name: manifestName, Mode: 0o644, Size: int64(len(b))})
	_, _ = tw.Write(b)

	payload := []byte(body)
	hdr := &tar.Header{
		Name: filesPrefix + name,
		Mode: 0o644,
		Size: int64(len(payload)),
	}
	if err := tw.WriteHeader(hdr); err != nil {
		return err
	}
	_, err = tw.Write(payload)
	return err
}

func readManifest(t *testing.T, path string) manifest {
	t.Helper()
	f, err := os.Open(path)
	if err != nil {
		t.Fatal(err)
	}
	defer f.Close()
	gz, err := gzip.NewReader(f)
	if err != nil {
		t.Fatal(err)
	}
	defer gz.Close()
	tr := tar.NewReader(gz)
	for {
		hdr, err := tr.Next()
		if err == io.EOF {
			t.Fatal("manifest missing")
		}
		if err != nil {
			t.Fatal(err)
		}
		if hdr.Name == manifestName {
			var man manifest
			if err := json.NewDecoder(tr).Decode(&man); err != nil {
				t.Fatal(err)
			}
			return man
		}
		_, _ = io.Copy(io.Discard, tr)
	}
}

func mustMkdir(t *testing.T, path string) {
	t.Helper()
	if err := os.MkdirAll(path, 0o755); err != nil {
		t.Fatal(err)
	}
}

func mustWrite(t *testing.T, path, body string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(body), 0o644); err != nil {
		t.Fatal(err)
	}
}

func assertFile(t *testing.T, path, want string) {
	t.Helper()
	got, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if string(got) != want {
		t.Fatalf("%s: got %q want %q", path, got, want)
	}
}
