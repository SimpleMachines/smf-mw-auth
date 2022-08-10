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

    $wgFSPath = '/path/to/smf/root/';

### Forum software.  Supports smf2.0, smf2.1, elk1.0, elk1.1

    $wgFSPSoftware = 'smf2.1';

# Optional LocalSettings
### Login Groups - Users in this group are signed into MediaWiki.  Group 2 in SMF is a fake group relating to all users.

    $wgFSPAllowGroups = array(5);

### Deny Groups - Prevent users in these groups from being signed into MediaWiki, this is a deny group and takes over the login group. In SMF group 4 is the Newbie group.

    $wgFSPDenyGroups = array(4);

### Admin Groups - Users in these groups are granted sysop access in MediaWiki.

    $wgFSPAdminGroups = array(1, 3);

### Super Groups - Users in these groups are granted bureaucrat access in MediaWiki.

    $wgFSPSuperGroups = array(1);

### Interface Groups - Users in these groups are granted interface-admin access in MediaWiki.

    $wgFSPInterfaceGroups = array(1);

### Special Groups - An key-valued array of smf_group_id => mediawiki_group_name

    $wgFSPSpecialGroups = array(
    	3 => 'special',
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

### Login Groups - Users in this group are signed into MediaWiki.  Group 2 in SMF is a fake group relating to all users.

    $wgSMFGroupID = array(2);

### Deny Groups - Prevent users in these groups from being signed into MediaWiki, this is a deny group and takes over the login group. In SMF group 4 is the Newbie group.

    $wgSMFDenyGroupID = array(4);

### Admin Groups - Users in these groups are granted sysop access in MediaWiki.

    $wgSMFAdminGroupID = array(1, 3);

### Special Groups - An key-valued array of smf_group_id => mediawiki_group_name

    $wgSMFSpecialGroups = array(
    	3 => 'special',
    );

### Forum Software Cookie.

    $wgCookieDomain = 'domain.tld';

Troubleshooting
---------------

Set $wgDebugLogFile in your LocalSettings.php:

    $wgDebugLogFile = "/some/private/path/mediawiki.log";
    
Search for ForumSessionProvider and it will tell you what it is thinking.

This bloats pretty quickly, so you'll want to comment it out after you have resolved your problem.

----
Getting New SMF Forks In
------------------------
If you are familiar with how your fork's authentication works, feel free to submit a pull request.

Issues or changes
------------------------
If an issue has occurred, please open a new issue.  If you have a change, please submit a pull request.
