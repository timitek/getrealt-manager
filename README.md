# getrealt-manager

An application installer for GetRealT.

***

# Installing

GetRealT manager utilizes [php 7](<http://php.net/>), [composer](<https://getcomposer.org/>) and [npm](<https://www.npmjs.com/>) to install it's components.  Make sure you have these installed installed before continuing.

You can install the manager either locally and run it from the cloned repository, or globally in composers CLI tool set to make it available everywhere.

**Installing Globally**

```
composer global require "timitek/getrealt-manager"
```

Make sure to place the ```$HOME/.composer/vendor/bin``` directory (or the equivalent directory for your OS) in your $PATH so the ```getrealt``` executable can be located by your system.

**Installing Locally**

If you would prefer not to install getrealt-manager into your composer's home globally , you may install it locally and execute it from this local directory.

Choose the directory you would like to install int into and clone the repository;

```
git clone https://github.com/timitek/getrealt-manager.git
```

Make sure to either place this new directory into your $PATH, or create the appropriate alias in your .bashrc so that you can execute it.

Example..

```
alias getrealt="/home/[the path where it was installed]/getrealt-manager/getrealt"
```


***

# How To Use

The GetRealT manager allows you to create a fresh installation of GetRealT or perform maintenance on existing installations.

## Installing a new GetRealT site

To create a clean installation of a new GetRealT site or update an existing installation use the ```getrealt add``` command.

**Usage**
getrealt [options] < name >

*example*

```
getrealt add mysite
```

Then follow the prompts.

## Updating an existing GetRealT site

To update an existing site, use the --update option.

```
getrealt add mysite --update
```

If you don't want to change any of the settings during the update, but are only interested in applying updates, you can supply an answer file (typically this is the existing .env file), in order to suppress being prompted.

```
getrealt add mysite --update --answerfile=./<mysite>/.env
```


## Getting Help

To display help for getrealt at any time you can type;

```
getrealt help
```

To get help for a specific command;

```
getrealt help add
```


## List Of Commands

To display a list of available commands

```
getrealt help
```
