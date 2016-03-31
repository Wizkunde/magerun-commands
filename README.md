# n98-magerun custom commands

These n98-magerun custom commands have been created to help my customers overcoming all kinds of store issues.
If you have new commands that you use a lot, please fork, create a PR and make this library even more useful!

I will add new custom commands as i go as they are a REAL timesaver!

## What is n98-magerun?

N98-magerun is a CLI package that adds a LOT of useful magento commands to the power of your command line.
You can find the package here: https://github.com/netz98/n98-magerun

After you installed it in your path, you can download this in your magento root to add the commands to your magento installation.

## Setup

Clone this repository and copy the lib folder recursively to your magento root. Thats all!

## General information

Mode explanation
- dry - Do not actually do anything, log as if you did.
- test - Set 1 sku with the --sku command and run this command only for that specific SKU
- live - Do all the magic of the command
- rollback - Undo the major fuckup you just made (given the fact that you actually created the backup as suggested)
 
## Extra credits

Inspired by Peter Jaap Blaakmeer from Elgentos his repository (https://github.com/peterjaap/magerun-addons), i built this one to provide me with similar functionalities and adding rollback support to the media part

## Available custom commands
- wizkunde:cache:warm - Warms the cache based on the Sitemap files generated in your installation
- wizkunde:media:remove-duplicates - Removes all duplicate files from your media gallery
- wizkunde:media:remove-orphans - Removes files that are in your filesystem but not in the database
- wizkunde:media:remove-missing - Removes files that are in your database but not in your filesystem

## wizkunde:cache:warm

```bash
Usage:
  wizkunde:cache:warm [options] [--] [<mode>]

Arguments:
  mode                       live, test or dry (default)

Options:
  -s, --sitemap=SITEMAP      Sitemap file path
```

## wizkunde:media:remove-duplicates

```bash
Usage:
  wizkunde:media:remove-duplicates [options] [--] [<mode>]

Arguments:
  mode                       rollback, live, test or dry (default)

Options:
  -s, --sku=SKU              Sku for when in test mode
```

## wizkunde:media:remove-orphans

```bash
Usage:
  wizkunde:media:remove-orphans [options] [--] [<mode>]

Arguments:
  mode                       rollback, live, dry (default)

Options:
  -s, --sku=SKU              Sku for when in test mode
```

## wizkunde:media:remove-missing

```bash
Usage:
  wizkunde:media:remove-missing [options] [--] [<mode>]

Arguments:
  mode                       live, test or dry (default)

Options:
  -s, --sku=SKU              Sku for when in test mode
```