Archive Repertory (plugin for Omeka)
====================================


Summary
-------

[Archive Repertory] is a plugin for [Omeka] that allows to keep original names
of imported files and to put them the simple and hierarchical structure
collection / item / files, in order to get readable urls for files and to avoid
an overloading of the file server.

In case of a duplicate name is detected, an index is added to the filename as a
suffix. Check is done on the basename, without extension, to avoid issues with
derivatives files.

Note: this plugin does not use the storage system of Zend/Omeka and modifies
only the archive file name.


Installation
------------

Uncompress files and rename plugin folder "ArchiveRepertory".

Then install it like any other Omeka plugin and follow the config instructions.


Unicode filenames
-----------------

As Omeka, the plugin works perfectly with filenames with Unicode characters, but
all the system, database, filesystem and php web/cli environment on the server
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

This plugin simplifies direct access to your files. That's not a main issue if
they are in public domain or you don't care about hotlinking and bandwidth
theft. If you want to protect them, you can adapt the following code to your
needs and it in your root ".htaccess" file, or in a ".htaccess" file in the
"files" folder or in the "files/original" folder:

```
Options +FollowSymlinks
RewriteEngine on
RewriteRule ^files/original/(.*)$ http://www.example.com/archive-repertory/download/file?filename=$1 [NC,L]
```

In this example, all original files will be protected: a check will be done by
the plugin before to deliver files. If the size of the file is bigger than a
specified size, set in the configuration page of the plugin, a confirmation
page will be displayed. The "mode" argument allows to set the download mode:
"inline" (default if no confirmation), "attachment" (always when a confirmation
is needed), "size", "image" or "image-size", according to your needs.
The file type is "original" by default, but other ones (fullsize...) can be
used. Note that if a confirmation is needed for fullsize images, the site may be
unusable.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database so you can roll back
if needed.


Troubleshooting
---------------

See online issues on the [Archive Repertory issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user's
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

* Copyright Daniel Berthereau, 2012-2014


[Omeka]: http://www.omeka.org
[Archive Repertory]: https://github.com/Daniel-KM/ArchiveRepertory
[Archive Repertory issues]: https://github.com/Daniel-KM/ArchiveRepertory/Issues
[CeCILL v2.1]: http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: http://github.com/Daniel-KM "Daniel Berthereau"
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr
[Mines ParisTech]: http://bib.mines-paristech.fr
