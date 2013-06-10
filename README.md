Archive Repertory (plugin for Omeka)
====================================


Summary
-------

This plugin for [Omeka] allows to keep original names of imported files and put
them in a hierarchical structure (collection / item / files) in order to get
readable urls for files and to avoid an overloading of the file server.

Note: this plugin does not use the storage system of Zend/Omeka and modifies
only the archive file name.


Installation
------------

Uncompress files and rename plugin folder "ArchiveRepertory".

Then install it like any other Omeka plugin and follow the config instructions.

This plugin is compatible with Omeka 2.1. To make this release compatible with
Omeka 2.0, a two lines patch should be applied on one file of Omeka core. For
more information, see the accepted commit [get_derivative_filename]. Or simply
update the line 269 of the file `application/models/File.php` (function
`getDerivativeFilename()`):
replace `$filename = basename($this->filename);`
with    `$filename = $this->filename;`.


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
images or to get mime type from files. After, you have four possibilities:

- use only filenames with standard Ascii characters;
- set options to auto convert files and folders names to Ascii;
- change the configuration of the server if you have access to it;
- replace every `escapeshellarg()` (present in eight files in Omeka core) with
`escapeshellarg_special()` and add the following code in `application/libraries/globals.php`:

```
/**
 * An ugly, non-ASCII-character safe replacement of escapeshellarg().
 *
 * @see http://www.php.net/manual/function.escapeshellarg.php
 */
function escapeshellarg_special($string) {
  return "'" . str_replace("'", "'\\''", $string) . "'";
}
```


Warning
-------

Use it at your own risk.

It's always recommended to backup your database so you can roll back if needed.

Furthermore, currently, no check is done on the name of files, so if two files
have the same name and are in the same folder, the second will overwrite the
first.

Troubleshooting
---------------

See online issues on the [Archive Repertory issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2] licence, compatible with
[GNU/GPL].

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


Copyright
---------

* Copyright Daniel Berthereau, 2012-2013


[Omeka]: http://www.omeka.org "Omeka.org"
[Archive Repertory issues]: https://github.com/Daniel-KM/ArchiveRepertory/Issues "GitHub Archive Repertory"
[CeCILL v2]: http://www.cecill.info/licences/Licence_CeCILL_V2-en.html "CeCILL v2"
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html "GNU/GPL v3"
[Daniel-KM]: http://github.com/Daniel-KM "Daniel Berthereau"
[École des Ponts ParisTech]: http://bibliotheque.enpc.fr "École des Ponts ParisTech / ENPC"
[get_derivative_filename]: https://github.com/Daniel-KM/Omeka/commit/f716af19b3be6d7e0ca77d36c08e409c4935b61c "commit get_derivative_filename"
