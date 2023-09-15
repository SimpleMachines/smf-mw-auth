This MediaWiki extension allows users in an [Elkarte Forum](https://www.elkarte.net/) or [SMF forum](https://www.simplemachines.org/) to be automatically signed in if they are of the appropriate usergroup while logged into the forum.

---
# Branches
| Branch | MediaWiki | Elkarte | SMF     |
| ------ | --------- | ------- | ------- |
| master | 1.38+     | 1.0,1.1 | 2.0,2.1 |
| mw135  | 1.35      | 1.0,1.1 | 2.0,2.1 |

 ----
# Configuration
To use, the contents of the ForumSsoProvider directory need to be placed into extensions/ForumSsoProvider. It is then loaded using the 'new' plugin loading method in LocalSettings.php:

    wfLoadExtension('ForumSsoProvider');

# Required LocalSettings
All settings should be defined prior to calling `wfLoadExtension('ForumSsoProvider');` in your LocalSettings.php
### Path to Forum Software

    $wgFSPPath = '/path/to/smf/root/';

### Forum software.  Supports smf2.0, smf2.1, elk1.0, elk1.1

    $wgFSPSoftware = 'smf2.1';

# Optional LocalSettings
### Login Groups - Users in this group are signed into MediaWiki.  SMF does not have a real group for "Regular" members, it is a pseudo group.  Additionally, Local Moderators (Group ID 3) is a special group.  Users are only in this group when they are on a board they have been granted moderator permissions on.  If you do not specify this, users are granted permission to the wiki by default.  If you specify this, users must be in the associated groups to have access to the wiki.

    $wgFSPAllowGroups = array(5);

### Deny Groups - Prevent users in these groups from being signed into MediaWiki, this is a deny group and takes over the login group.

    $wgFSPDenyGroups = array(4);

### Admin Groups - Users in these groups are granted sysop access in MediaWiki.

    $wgFSPAdminGroups = array(1, 2);

### Super Groups - Users in these groups are granted bureaucrat access in MediaWiki.

    $wgFSPSuperGroups = array(1);

### Interface Groups - Users in these groups are granted interface-admin access in MediaWiki.

    $wgFSPInterfaceGroups = array(1);

### Special Groups - An key-valued array of {SMF Group ID} => {MediaWiki Group Name}

    $wgFSPSpecialGroups = array(
    	11 => 'Custom_Wiki_group',
    );

### Ban checks - Enable checking against bans in SMF.  If found it prevents access to MediaWiki.

    $wgFSPEnableBanCheck = true;

### Lockdown permissions to prevent new account creations/modifications.

    $wgGroupPermissions['*']['createaccount']     = false;
    $wgGroupPermissions['*']['read']              = true;
    $wgGroupPermissions['*']['edit']              = false;
    $wgGroupPermissions['*']['createtalk']        = false;
    $wgGroupPermissions['*']['createpage']        = false;
    $wgGroupPermissions['*']['writeapi']          = false;
    $wgGroupPermissions['user']['move']           = true;
    $wgGroupPermissions['user']['read']           = true;
    $wgGroupPermissions['user']['edit']           = true;
    $wgGroupPermissions['user']['upload']         = true;
    $wgGroupPermissions['user']['autoconfirmed']  = true;
    $wgGroupPermissions['user']['emailconfirmed'] = true;
    $wgGroupPermissions['user']['createtalk']     = true;
    $wgGroupPermissions['user']['createpage']     = true;
    $wgGroupPermissions['user']['writeapi']       = true;

# Legacy Settings
These settings are used by the legacy Auth_SMF.php.
### Uses the legacy Auth_SMF.php LocalSettings

    define('SMF_IN_WIKI', true);
    $wgSMFLogin = true;

### Login Groups - Users in this group are signed into MediaWiki.

    $wgSMFGroupID = array(2);

### Deny Groups - Prevent users in these groups from being signed into MediaWiki, this is a deny group and takes over the login group.

    $wgSMFDenyGroupID = array(4);

### Admin Groups - Users in these groups are granted sysop access in MediaWiki.

    $wgSMFAdminGroupID = array(1, 2);

### Special Groups - An key-valued array of {SMF Group ID} => {MediaWiki Group Name}

    $wgSMFSpecialGroups = array(
    	11 => 'Custom_Wiki_group',
    );

### Forum Software Cookie.

    $wgCookieDomain = 'domain.tld';

SMF Default Groups
---------------
| Group ID | Group Name | Post Group |
| ---- | --------- | --- |
| 1 | Administrator | No |
| 2 | Global Moderator | No |
| 4 | Newbie | Yes |
| 5 | Jr. Member | Yes |
| 6 | Full Member | Yes |
| 7 | Sr. Member | Yes |
| 8 | Hero Member | Yes |

Finding your SMF Group ID
---------------
1. Navigate to the Admin Control Panel
2. Click on Membergroups
3. Click Modify on the group you are looking for.
4. In the address bar, you will see `group=####`, this number is the group id.

Working with Arrays
---------------
The configuration file uses basic PHP code.

When you have a single member for the array, you simply just wrap it in the array statement
    $code = array(1);


If you have 2 members that need to go into the array, use a comma to separate them.
    $code = array(1,2);

For strings, wrap them in quotes. SMF coding style recommends using single quotes unless necessary. Double quotes signal the PHP parser to use special handling, which might interpret variables and other logic inside the string. This is typically not necessary in the configuration.
    $code = array('my string');

You may also see the shorthand square brackets to refer to arrays.  This is valid as well, but not used in the configuration for simplificy.
    $code = ['my string'];

Extension Troubleshooting
---------------

Set $wgDebugLogFile in your LocalSettings.php:

    $wgDebugLogFile = "/some/private/path/MediaWiki.log";
 
Search for ForumSessionProvider and it will tell you what it is thinking.

This bloats pretty quickly, so you'll want to comment it out after you have resolved your problem.

Wiki Troubleshooting
---------------
MediaWiki has built in methods for debugging it.  If the extension is acting up and the debugging log is not providing information.  Add the following to your LocalSettings.php.  This should not be run in a production forum as it may expose sensitive details

	$wgShowExceptionDetails = true;
	$wgShowSQLErrors = true;
	$wgDebugDumpSql  = true;
	$wgShowDBErrorBacktrace = true;
Remove when you are done debugging.

----
Getting New SMF Forks In
------------------------
If you are familiar with how your fork's authentication works, feel free to submit a pull request.

Issues or changes
------------------------
If an issue has occurred, please open a new issue.  If you have a change, please submit a pull request.

