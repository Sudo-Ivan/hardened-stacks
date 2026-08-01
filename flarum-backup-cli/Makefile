APP := flarum-backup
VERSION ?= $(shell git describe --tags --always --dirty 2>/dev/null || echo dev)
COMMIT ?= $(shell git rev-parse --short HEAD 2>/dev/null || echo none)
GO ?= go
GOSEC ?= gosec

.PHONY: all build test fmt fix vet gosec check clean install

all: check build

build:
	$(GO) build -trimpath -ldflags="-s -w -X main.version=$(VERSION) -X main.commit=$(COMMIT)" -o bin/$(APP) .

test:
	$(GO) test -count=1 -race ./...

fmt:
	$(GO) fmt ./...

fix:
	$(GO) fix ./...
	@test -z "$$($(GO) fix -diff ./... 2>/dev/null || true)"

vet:
	$(GO) vet ./...

gosec:
	@command -v $(GOSEC) >/dev/null 2>&1 || $(GO) install github.com/securego/gosec/v2/cmd/gosec@v2.22.10
	$(GOSEC) -quiet -exclude-generated ./...

check: fmt fix vet test gosec

install: build
	install -m 0755 bin/$(APP) "$(DESTDIR)/usr/local/bin/$(APP)"

clean:
	rm -rf bin
