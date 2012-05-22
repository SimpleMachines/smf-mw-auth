SMF and MediaWiki Integration
=============================
Author: SleePy (sleepy at simplemachines dot org)
Original Author: Ryan Wagoner (rswagoner at gmail dot com)
Version: 1.14
Last Modification Date: 2012.04.01
Last Modified By: J. Noir (jnoir at jnoir dot eu)

How does it work?
=================

Auth_SMF.php manages the Authentication between MediaWiki and an
already existing SMF forum. By means of cookies and their management
by Auth_SMF.php, SMF and MediaWiki can "talk" one to each other, and
therefore seamlessly bridge both environments.

Users are created in MediaWiki the first time they access the Wiki
when logged into the forum also. In other words, when a registered
user from your forum is logged in, and access the wiki for the first
time, he/she will be automatically and immediately registered and logged
into the wiki with the pre-assigned rights.

This extension provides just the framework for bridging MediaWiki and
SMF: each administrator is expected to perform the integration (themes,
linking, etc.)

Pre-requisites
==============

Before you start, it is recommended that you:

- Read this file completely and understand all the steps required

- Use the same database for MediaWiki and SMF. Use a prefix such as 
wiki_ to identify MediaWiki's tables.

- Have a fully working, independent MediaWiki installation in the server.

- Perform a full backup of files and database(s). A restore should not be
required, but it does not hurt to be on the safe side.


How to install
==============

Follow this instructions to bridge MediaWiki and SMF.

1- Download Auth_SMF.php to your computer and then upload this file in 
the 'extensions' folder of your MediaWiki installation. 

2.- Download the file LocalSettings.php that is located in the root
folder of your MediaWiki installation.

3.- Edit LocalSettings.php (but make sure not use Notepad, TextEdit or
other text editor that adds byte order marks to files, or you will
break your wiki). See http://en.wikipedia.org/wiki/Byte_order_mark
for more information about byte order marks.

4.- If you have setup your wiki to require users to be registered 
before being allowed to edit or create pages, the following entry 
should be already created in LocalSettings.php:

# This requires a user be logged into the wiki to make changes.
$wgGroupPermissions['*']['edit'] = false; // MediaWiki Setting

5.- Scroll down to the end of the file, then copy and paste the
following content:

# If you experience the issue where you appear to be logged in
# eventhough you are logged out then disable the page cache.
#$wgEnableParserCache = false;
#$wgCachePages = false;

# SMF Authentication
# To get started you only need to configure wgSMFPath. 
# The rest of the settings are optional for advanced features.

# Relative path to the forum directory from the wiki
# Do not put a trailing /
# Example: /public_html/forum and /public_html/wiki -> ../forum
$wgSMFPath = "../forum"; 

# Use SMF's login system to automatically log you in/out of the wiki
# This works best if you are using SMF database sessions (default).
# Make sure "Use database driven sessions" is checked in the
# SMF Admin -> Server Settings -> Feature Configuration section
# NOTE: Make sure to configure the $wgCookieDomain below
#$wgSMFLogin = true;
#$wgCookieDomain = 'domain.com';

# Members in these SMF groups will not be allowed to sign into wiki.
# This is useful for denying access to wiki and a easy anti-spam
# method.  The group ID, which can be found in the url (;group=XXX)
# when viewing the group from the administrator control panel.
#$wgSMFDenyGroupID = array(4);

# Grant members of this SMF group(s) access to the wiki
# NOTE: The wgSMFDenyGroupID group supersedes this.
#wgSMFGroupID = array(2);

# Grant members of this SMF group(s) wiki sysop privileges
# NOTE: These members must be able to login to the wiki
#$wgSMFAdminGroupID = array(1, 3);

# SMF to wiki group translation.  This allows us to assign wiki groups
# to those in certain SMF groups.
#$wgSMFSpecialGroups = array(
#  // SMF Group ID => Wiki group name,
#	5 => 'autoconfirmed'
#);

# THIS MUST BE ADDED.  This prevents direct access to the Auth file.
define('SMF_IN_WIKI', true);

# Load up the extension
require_once "$IP/extensions/Auth_SMF.php";
$wgAuth = new Auth_SMF();

6.- Upload the LocalSettings.php and overwrite the existing one 
(remember that it is always a good idea to have a backup)

7.- Test your integration. If everything went fine, you should have
full authentication bridging. If not, continue reading for some
troubleshooting guidance.

Known Issues / FAQ
==================

Q.- The authentication does not detect my cookies/I do not get logged in.

A.- Check your SMF cookie settings in ACP -> Configuration -> Server Settings -> Cookies and Sessions.  You will need to disable local storage of cookies.  If the wiki is on a different subdomain, you will need to enable subdomain independent cookies.  This auth can not work cross domain (i.e. domainA.com to domainB.com) as it violates security controls in browsers.

Q.- Authentication is working and permissions are being granted, however 
when I try to edit pages, I receive a message edition was not successful 
due to loss of session data. It recommends to log off and login again, 
but that does not help.

A.- Make sure that $wgCookieDomain is set to the name of your domain
without prefixes (e.g.: if your forum is located at http://www.myforum.com
then it should be configured as $wgCookieDomain = 'myforum.com'; )

Q.- I cannot login with the Administrator account created during MediaWiki's 
installation

A.- This is the expected behavior. The account created at installation time
is part of MediaWiki, not of your forum. This extension bridges SMF to
MediaWiki, but not the other way round (in other words, SMF is the principal
and MediaWiki is a student ;-)

Q.- I can authenticate with my forum's administrator, however I cannot see how
to assign some extra rights for some users (e.g., for forum moderators).

A.- That's part of the 'bureaucrat' role. You will need to assign your
administrator to that role first. For some possible techniques, see 
http://www.mediawiki.org/wiki/Manual:Setting_user_rights_in_MediaWiki

Q.- How can I link my wiki from my forum ?

A.- See http://www.simplemachines.org/community/index.php?topic=261880.0

Q.- How can I "bridge" the favicon from my forum ?

A.- Edit LocalSettings.php and use $wgFavicon = "/path/to/your/icon.ico";

Notes
==================

Feel free to fork this repository and make your desired changes.

Please see the "Developer's Certificate of Origin"(https://github.com/SimpleMachines/smf-mw-auth/blob/master/DCO.txt) in the repository:
by signing off your contributions, you acknowledge that you can and do license your submissions under the license of the project.

How to contribute
==================
1. fork the repository. If you are not used to Github, please check out "fork a repository"(http://help.github.com/fork-a-repo).
2. branch your repository, to commit the desired changes.
3. sign-off your commits, to acknowledge your submission under the license of the project.
  Note: an easy way to do so, is to define an alias for the git commit command, which includes -s switch (reference: "How to create Git aliases"(http://githacks.com/post/1168909216/how-to-create-git-aliases))
4. send a pull request to us.
