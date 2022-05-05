# Installation of RoundCube CalDAV plugin

There is one way to install the plugin,  using composer with libraries globally managed across the entire roundcube installation (__recommended__)


After installation, you need to [configure](#configuration) the plugin.

## Installation using composer

The recommended and supported method of installation is by using composer.

Installation steps (all paths in the following instructions are relative to the _root directory_ of your roundcube
installation):

- Get [composer](https://getcomposer.org/download/)
- go to your roundcude/plugins directory 
- clone this module ' git clone https://github.com/scopen-coop/roundcube_caldav.git ' 
- cd roundcube_caldav
- run composer update 
- Enable RoundCube CalDAV in Roundcube:
  Open the file `config/config.inc.php` and add `roundcube_caldav` to the array `$config['plugins']`.
- Login to Roundcube and setup your caldav server by navigation to the Settings page and click on Setup CalDav.

In case of errors, check the files `logs/*`.

## Installation with roundcube installed from Debian/Ubuntu repositories

The version of roundcube packaged by Debian and distributed through the Debian and Ubuntu repositories has a split
installation scheme that is probably needed to comply with the Debian packaging guidelines.
  - The static part of roundcube is installed to `/usr/share/roundcube`
  - The files that may need to be modified are placed in `/var/lib/roundcube`
  - The plugins are searched for in `/var/lib/roundcube/plugins`, some pre-installed plugins are actually stored with the
    static part and symlinked from the `plugins` directory.
