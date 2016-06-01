# Magma

A command line tool to deploy Symfony 2 (or 3) applications.

## Install

```
// download
curl -sS https://github.com/adadgio/magma/raw/master/build/magma-beta-v0.1.phar // or "magma-v{releaseVersion}.phar"

// rename make executable
chmod +x magma-v{releaseVersion}.phar

// move to bin so that you can use it anywhere (global install)
mv magma-v{releaseVersion}.phar /usr/local/bin/magma
```

## Usage

Type `magma` for general information, `magma list` for the available list of commands or `magma <command> --help`.

## Getting started

Magma uses rsync to deploy a project (this server SSH access must be set up). To get started, `cd` into a Symfony project and create a `magma.yml` file.

```
# magma.yml

project:
    name: "My project"
    environments:
        staging:
            remote:
                user: nelson
                host: MY.IP1.ETC.ETC
                port: 2121
                path: "/var/websites/myproject"
            post_deploy:
                - "composer update"
                - "php app/console cache:clear"
        prod:
            remote:
                user: ben
                host: MY.IP2.ETC.ETC
                port: 2121
                path: "/var/websites/myproject"
            post_deploy:
                - "composer update"

	# might vary depending on your Sf version
    shared: ["vendor/", "web/uploads"]
    exclude: ["vendor/", "web/uploads/", "box.json"]
    writable_folders: ["web/uploads", "var/logs"]
```
