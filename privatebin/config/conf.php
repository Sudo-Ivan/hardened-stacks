;<?php http_response_code(403); /*
; Hardened PrivateBin defaults for public paste hosting.
; Server stores ciphertext only (AES-256-GCM in the browser).

[main]
name = "Paste"
basepath = "http://127.0.0.1:8080/"
discussion = false
opendiscussion = false
password = true
fileupload = false
burnafterreadingselected = true
defaultformatter = "plaintext"
sizelimit = 2097152
templateselection = false
languageselection = false
httpwarning = true
compression = "zlib"
notice = "Pastes are encrypted in your browser. The server cannot read them. Do not share the full URL if the paste is sensitive unless you also set a password."

[expire]
default = "1day"

[expire_options]
5min = 300
10min = 600
1hour = 3600
1day = 86400
1week = 604800
1month = 2592000

[formatter_options]
plaintext = "Plain Text"
syntaxhighlighting = "Source Code"
markdown = "Markdown"

[traffic]
limit = 10
header = "X_FORWARDED_FOR"

[purge]
limit = 300
batchsize = 10

[model]
class = Filesystem
[model_options]
dir = PATH "data"
