# SlimX

SlimX is an Extra for the MODX CMS, which helps to create REST APIs connected to MIGX configs based on Slim Framework

## Installation

Install the transport-package by MODX package manager.

## Apache

Rename assets/components/slimx/ht.access

to assets/components/slimx/.htaccess

At your webroot (where MODX is installed) create the Entrypoint to your API with an .htaccess like that

api/.htaccess

```
RewriteEngine on
RewriteRule ^$ ../assets/components/slimx/ [L]
RewriteRule (.*) ../assets/components/slimx/$1 [L]
```

## Usage

