# Pantheon Build Tools Launcher

A small wrapper around Pantheon Build Tools for your global $PATH.

## Why?

In order to avoid dependency issues, it is best to require Pantheon Build Tools on a per-project basis via Composer (`composer require aweingarten/pbt`). This makes Pantheon Build Tools available to your project by placing it at `vendor/bin/pbt`.

However, it is inconvenient to type `vendor/bin/pbt` in order to execute Pantheon Build Tools commands.  By installing the pbt launcher globally on your local machine, you can simply type `pbt` on the command line, and the launcher will find and execute the project specific version of pbt located in your project's `vendor` directory.

## Installation - Phar

1. Download latest stable release via CLI (code below) or browse to https://github.com/pbt-ops/pbt-launcher/releases/latest.

    OSX:
    ```Shell
    curl -OL https://github.com/aweingarten/pbt-launcher/releases/latest/download/pbt.phar
    ```

    Linux:

    ```Shell
    wget -O pbt.phar https://github.com/aweingarten/pbt-launcher/releases/latest/download/pbt.phar
    ```
1. Make downloaded file executable: `chmod +x pbt.phar`
1. Move pbt.phar to a location listed in your `$PATH`, rename to `pbt`:

    ```Shell
    sudo mv pbt.phar /usr/local/bin/pbt
    ```

1. Windows users: create a pbt.bat file in the same folder as pbt.phar with the following lines. This gets around the problem where Windows does not know that the `pbt` file is associated with `php`:

    ``` Bat
    @echo off
    php "%~dp0\pbt" %*
    ```

## Update

The Pantheon Build Tools Launcher Phar is able to self update to the latest release.

```Shell
    pbt self-update
```

## Alternatives

If you only have one codebase on your system (typical with VMs, Docker, etc,), you should add `/path/to/vendor/bin` to your `$PATH`. Pantheon Build Tools 10 is smart enough to find the `PROJECT_ROOT` and `DRUPAL_ROOT` when it is run from the bin directory.

## Fallback

When a site-local Pantheon Build Tools is not found, this launcher usually throws a helpful error.
You may avoid the error and instead hand off execution to a global Pantheon Build Tools (any version)
by exporting an environment variable.

`export pbt_LAUNCHER_FALLBACK=/path/to/pbt`

## Xdebug compatibility

Pantheon Build Tools Launcher, like Composer automatically disables Xdebug by default. This improves performance substantially. You may override this feature by setting an environment variable. ``PBT_ALLOW_XDEBUG=1 pbt [command]``

## License

GPL-2.0+

## Credit / Kudos
Based on the Drush Launcher by the Drush Team
