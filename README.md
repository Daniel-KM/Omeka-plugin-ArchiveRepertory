
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

Current release is compatible with Omeka 1.5.3, but a little patch should be
applied on two files of Omeka core, waiting for its official integration. For
more information, see the commit [set_derivative_filename][7].


Warning
-------

Use it at your own risk.

It's always recommended to backup your database so you can roll back if needed.


Troubleshooting
---------------

See online issues on [GitHub][2].


License
-------

This plugin is published with a double licence:

### [CeCILL][3]

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

### [GNU/GPL][4]

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel_KM][5])

First version of this plugin has been built for École des Ponts ParisTech
(see [ENPC][6]).


Copyright
---------

* Copyright Daniel Berthereau for École des Ponts ParisTech, 2012


[1]: http://www.omeka.org "Omeka.org"
[2]: https://github.com/Daniel-KM/ArchiveRepertory/Issues "GitHub ArchiveRepertory"
[3]: http://www.cecill.info/licences/Licence_CeCILL_V2-en.html "CeCILL"
[4]: https://www.gnu.org/licenses/gpl-3.0.html "GNU/GPL"
[5]: http://github.com/Daniel-KM "Daniel_KM"
[6]: http://bibliotheque.enpc.fr "École des Ponts ParisTech"
[7]: https://github.com/Daniel-KM/Omeka/commit/f2ac2f50f3219973a228ecc2db52a676a852e743 "commit set_derivative_filename"