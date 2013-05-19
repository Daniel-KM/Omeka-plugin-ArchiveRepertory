
Archive Repertory (plugin for Omeka)
====================================


Summary
-------

This plugin allows to keep original names of imported files and put them in a
hierarchical structure (collection / item / files) in order to get readable urls
for files and to avoid an overloading of the file server.

Note: this plugin does not use the storage system of Zend/Omeka and modifies
only the archive file name.

For more information on Omeka, see [Omeka][1].


Installation
------------

Uncompress files and rename plugin folder "ArchiveRepertory".

Then install it like any other Omeka plugin and follow the config instructions.

Current release is compatible with Omeka 2.0, but a little patch should be
applied on one file of Omeka core, waiting for its official integration. For
more information, see the proposed commit [get_derivative_filename][7].

The plugin works perfectly with filenames with Unicode characters, but all the
system and the web environment on the server should be set according to this
format. If derivative files are not created, check the behavior of the php
function "escapeshellarg()", largely used in Omeka, or use only filenames with
standard Ascii characters.


Warning
-------

Use it at your own risk.

It's always recommended to backup your database so you can roll back if needed.


Troubleshooting
---------------

See online issues on [GitHub][2].


License
-------

This plugin is published under the [CeCILL v2][3] licence, compatible with
[GNU/GPL][4].

In consideration of access to the source code and the rights to copy,
modify and redistribute granted by the license, users are provided only
with a limited warranty and the software's author, the holder of the
economic rights, and the successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying
and/or developing or reproducing the software by the user are brought to
the user's attention, given its Free Software status, which may make it
complicated to use, with the result that its use is reserved for
developers and experienced professionals having in-depth computer
knowledge. Users are therefore encouraged to load and test the
suitability of the software as regards their requirements in conditions
enabling the security of their systems and/or data to be ensured and,
more generally, to use and operate it in the same conditions of
security. This Agreement may be freely reproduced and published,
provided it is not altered, and that no provisions are either added or
removed herefrom.


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM][5])

First version of this plugin has been built for [École des Ponts ParisTech][6].


Copyright
---------

* Copyright Daniel Berthereau, 2012-2013


[1]: http://www.omeka.org "Omeka.org"
[2]: https://github.com/Daniel-KM/ArchiveRepertory/Issues "GitHub ArchiveRepertory"
[3]: http://www.cecill.info/licences/Licence_CeCILL_V2-en.html "CeCILL v2"
[4]: https://www.gnu.org/licenses/gpl-3.0.html "GNU/GPL v3"
[5]: http://github.com/Daniel-KM "Daniel Berthereau"
[6]: http://bibliotheque.enpc.fr "École des Ponts ParisTech / ENPC"
[7]: https://github.com/Daniel-KM/Omeka/commit/5fcb88adcda51b43abc2565e7b279edaa614f5dd "commit get_derivative_filename"
