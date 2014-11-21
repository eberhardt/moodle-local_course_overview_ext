Extended Course Overview
==========================

Extends the block course_overview in that way, that it shows recent changes in subscribed courses.
The behavior is similiar to the block recent_activities.

Also give the users some options regarding the shown items.

Patches
=========

The patches in the directory patches have to be applied in respect to your Moodle version, since these changes alter the core code.
This has the advantage, that most core changes, which doesn't affect the changed lines of the original plugin, will be merged by GIT automatically.
If there is an conflict between the changes of the patch and Moodle updates, use only Moodle updates.
An appropriate patch will be given in future releases.

On a linux server go to your Moodle root directory and type
`patch -p1 < local/course_overview_ext/patches/MOODLE_{version}.patch`

Change {version} with resprect to your Moodle version.

Contributions
===============

Patches will be generated only for the most recent Moodle versions.
That means, that there will be no furture patch for Moodle 2.7.
If you want to contribute an own patch, because an older version of Moodle is updated, feel free to share one.
Please follow some rules:
  * Patches have to be generated in the Moodle root directory
  * Please follow the naming example 'MOODLE_{version}.patch', where {version} is replaced by your Moodle version
  * Versions are represented that way:
    * Moodle X.Y.* => MOODLE_XZ.patch; If this file exists add an underscore and the next number:
    * Moodle X.Y.Z.* => MOODLE_XY_Z.patch; If this file exists as well, go on:
    * Moodle X.Y.Z.W.* => MOODLE_XY_Z_W.patch; If there is no number left, add the build number instead:
    * Moodle X.Y.Z.W (Build Q) => MOODLE_XY_Z_W_Q.patch

I recommend to use

`git diff moodle/MOODLE_XY_STABLE blocks/course_overview/ > local/course_overview_ext/patches/MOODLE_{version}.patch`

for generating the patch.
The resulting patch file will be valid for the command above.
Otherwise ensure to create a patch file that way, that the command above give the expected results.
