Archive Repertory (plugin for Omeka)
====================================

[![Build Status](https://travis-ci.org/Daniel-KM/ArchiveRepertory.svg?branch=master)](https://travis-ci.org/Daniel-KM/ArchiveRepertory)

[Archive Repertory] is a plugin for [Omeka] that allows Omeka to keep the
original names of imported files and to put them into a simple and hierarchical
structure consisting of: "collection / item / files", in order to get readable
urls for files and to avoid an overloading of the file server. A protection
against hotlinking and bandwidth theft can be set via htaccess.

See the example of the digitized heritage of the library of [Mines ParisTech].

This plugin is upgradable to [Omeka S] via the plugin [Upgrade to Omeka S], that
installs the module [Archive Repertory for Omeka S].


Notes
-----

If a duplicate name is detected, an index is added to the filename as a suffix.
Check is done on the basename, without extension, to avoid issues with
derivative files.

Duplicate names of collections and items don’t create issues, because each file
is managed individually. Nevertheless, to use identical names is not recommended,
because files of different items or collections will be mixed in the same
folder.

Currently, when a collection is moved, files are not moved until each item is
updated. The files are still available. This avoids a long process. To
update each folder, it’s possible to batch edit items without any operation, so
a job will be launched and files will be moved automatically.

Note: this plugin does not use the storage system of Zend/Omeka and modifies
only the archive file name, so this is kept compatible with any storage.

This plugin can be used with [Clean Url] for an improved user experience and
for a better search engine optimization.

The anti-hotlinking feature is compatible with the logger [Stats], that allows
to get stats about viewed pages and downloaded files.


Installation
------------

Uncompress files and rename plugin folder "ArchiveRepertory".

Then install it like any other Omeka plugin and follow the config instructions.

See below to configure the protection of files.

Tiles for big images created with the plugin [OpenLayers Zoom] are compatible
with plugin. To enable it, just open and submit the config page of the plugin
OpenLayers Zoom, the integration between the two plugins will be registered
automatically.


Unicode filenames
-----------------

As Omeka, the plugin works fine with filenames with Unicode characters, but all
the system, database, filesystem and php web/cli environment on the server
should be set according to this format.

An autocheck is done in the config page. You can check it too when you upload a
file with a Unicode name.

If derivative files with non Ascii names are not created, check the behavior of
the php function "escapeshellarg()", largely used in Omeka. The problem occurs
only when Omeka uses command line interface, in particular to create derivative
images or to get mime type from files. After, you have five possibilities:

- use only folder and file names with standard Ascii characters;
- set options to auto convert files and folders names to Ascii;
- change the configuration of the server if you have access to it
    To change an Apache server configuration, simply uncomment a line in the
    file "envvars" in order to use the system default locale, and add this
    just below to avoid numerical issues:
    `export LC_NUMERIC=C`
- add this in the beginning the "bootstrap.php" of Omeka (with your locale):
    `setlocale(LC_CTYPE, 'fr_FR.UTF-8');`
    `setlocale(LC_COLLATE, 'fr_FR.UTF-8');`
    Avoid `setlocale(LC_ALL, 'fr_FR.UTF-8')`, and keep numerical values as "C":
    `setlocale(LC_NUMERIC, 'C');`
- replace every `escapeshellarg()` (present in eight files in Omeka core) with
`escapeshellarg_special()` and every `basename()` with `basename_special()`.


Protecting your files
---------------------

This plugin simplifies direct access to your files. That’s not a main issue if
they are in public domain or you don’t care about hotlinking and bandwidth
theft.

Anyway, if you want to protect them, you can adapt the following code to your
needs in the root `.htaccess` file, or in a `.htaccess` file in the "files"
folder or in the "files/original" folder:

```
Options +FollowSymlinks
RewriteEngine on

RewriteRule ^files/original/(.*)$ http://www.example.com/archive-repertory/download/files/original/$1 [NC,L]
```

You can adapt `routes.ini` as you wish too.

In this example, all original files will be protected: a check will be done by
the plugin before to deliver files. If the size of the file is bigger than a
specified size set in the configuration page, a confirmation page will be
displayed.

The file type is "original" by default, but other ones (fullsize...) can be
used. Note that if a confirmation is needed for fullsize images, the site may be
unusable.

The "mode" argument in `routes.ini` allows to set the download mode:
"inline" (default if no confirmation), "attachment" (always when a confirmation
is needed), "size", "image" or "image-size", according to your needs.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [Archive Repertory issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

Current maintainers:
* Daniel Berthereau (see [Daniel-KM] on GitHub)

First version of this plugin has been built for [École des Ponts ParisTech].
The upgrade for Omeka 2.0 has been built for [Mines ParisTech].


Copyright
---------

* Copyright Daniel Berthereau, 2012-2017


[Archive Repertory]: https://github.com/Daniel-KM/ArchiveRepertory
[Omeka]: https://omeka.org
[Archive Repertory issues]: https://github.com/Daniel-KM/ArchiveRepertory/issues
[Clean Url]: https://github.com/Daniel-KM/CleanUrl
[Stats]: https://github.com/Daniel-KM/Stats
[Omeka S]: https://omeka.org/s
[Upgrade to Omeka S]: https://github.com/Daniel-KM/UpgradeToOmekaS
[Archive Repertory for Omeka S]: https://github.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[OpenLayers Zoom]: https://github.com/Daniel-KM/OpenLayersZoom
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: https://patrimoine.mines-paristech.fr
