Roundcube Webmail SieveRules
============================
This plugin adds the ability for users to manage their sieve mail filter rules.
Inspiration and most of the code for this plugin was taken from:
[Aleksander Machniak][alec] - original Roundcube managesieve patch
Tested with Dovecot-managesieve

ATTENTION
---------
This is just a snapshot from the GIT repository and is **NOT A STABLE version
of SieveRules**. It is Intended for use with the **GIT-master** version of
Roundcube and it may not be compatible with older versions. Stable versions of
SieveRules are available from the [Roundcube plugin repository][rcplugrepo]
(for 1.0 and above) or the [releases section][releases] of the GitHub
repository.

Requirements
------------
* [Roundcube jQueryUI plugin][rcjqui]
* PEAR Net_Sieve 1.3.2 or newer ([included in Roundcube core][netsieve])

Supported Extensions
--------------------
comparators
envelope
fileinto
imapflags/imap4flags
notify/enotify
regex
reject/ereject
relational
subaddress
vacation
vacation-seconds
body
copy
spamtest
virustest
variables
date
editheader

License
-------
This plugin is released under the [GNU General Public License Version 3+][gpl].

Even if skins might contain some programming work, they are not considered
as a linked part of the plugin and therefore skins DO NOT fall under the
provisions of the GPL license. See the README file located in the core skins
folder for details on the skin license.

Install
-------
* Place this plugin folder into plugins directory of Roundcube
* Add sieverules to $config['plugins'] in your Roundcube config

**NB:** When downloading the plugin from GitHub you will need to create a
directory called sieverules and place the files in there, ignoring the root
directory in the downloaded archive.

Config
------
The default config file is plugins/sieverules/config.inc.php.dist
Rename this to plugins/sieverules/config.inc.php
* Set the host where managesieve is listening
* Set the port managesieve is listening on
* (Optional) Set the folder delimiter (if your managesieve implementation uses
             a different one to your IMAP server
* Set the name of the ruleset in which your sieve rules are stored
* Enable/disable the actions you want
* (Optional) Define any predefined rules you wish to use
* (Optional) Define a default sieve rule file
* (Optional) Define an example sieve rule file

Predefined rules
----------------
The predefined rules option allows you specify a set of simple rules which are
available in the UI identified by their name.
All other rule options are hidden when a predefined rule is selected.

The following options are available in each field:

* type:
 * header
 * address
 * envelope
 * size
* operator:
 * for rules of type header, address, envelope one of:
  * 'contains' : contains
  * 'notcontains' : does not contain
  * 'is' : is equal to
  * 'notis' : is not equal to
  * 'exists' : exists
  * 'notexists' : does not exist
  * 'regex' : matches regular expression
  * 'notregex' : does not match regular expression
  * 'count "gt"': count is greater than
  * 'count "ge"': count is greater than or equal to
  * 'count "lt"': count is less than
  * 'count "le"': count is less than or equal to
  * 'count "eq"': count is equal to
  * 'count "ne"': count does not equal
  * 'value "gt"': value is greater than
  * 'value "ge"': value is greater than or equal to
  * 'value "lt"': value is less than
  * 'value "le"': value is less than or equal to
  * 'value "eq"': value is equal to
  * 'value "ne"': value does not equal
  * 'user': user part equals
  * 'notuser': user part does not equal
  * 'detail': detail part equals
  * 'notdetail': detail part does not equal
  * 'domain': domain part equals
  * 'notdomain': domain part does not equal
 * for rules of type size one of:
  * 'over' : is more than
  * 'under' : is less than
 * extra:
  with count or value operators a comparator can be specified, this can be any
  comparator supported by your server (i;ascii-casemap is default, to use this
  comparator the field should be left blank)

The name, header, and target fields do not have set values

Examples:

1. Simple spam filter:
```php
   array(
    'name' => 'Is Spam',
    'type' => 'header',
    'header' => 'X-Spam-Flag',
    'operator' => 'exists',
    'extra' => '',
    'target' => '')
```

2. Big message filter:
```php
   array(
    'name' => 'Big messages',
    'type' => 'size',
    'header' => '',
    'operator' => 'over',
    'extra' => '',
    'target' => '5M')
```

3. Spam score filter:
```php
   array(
    'name' => 'Definitely spam',
    'type' => 'header',
    'header' => 'X-Spam-Score',
    'operator' => 'value "ge"',
    'extra' => 'i;ascii-numeric',
    'target' => '10')
```

Advanced editor
---------------
The advanced editor allows users to edit the sieve file directly, without the
restrictions of the UI. Please note any changes made to the file directly which
cannot be parsed by the script will be lost if rules are saved in the normal
mode.

**IMPORTANT:** There is no validation of the script, please be careful when
editing it directly or you could break all your filtering!

Default sieve rule file
-----------------------
If a default sieve rule file is specified then when a user has no sieve rules
defined this file is loaded instead and the rules are displayed just as if they
belong to the user. The file can be stored any where on your server and the
user under which your web server runs must have permission to read it.

Example sieve rule file
-----------------------
If an example sieve rule file is specified then the filters from this script
are loaded and displayed in a drop down in the bottom right of the screen.
Users can select one of these example rules, it will load in the just as one of
their rules, edit it and then save it to their rule set. The file can be stored
any where on your server and the user under which your web server runs must
have permission to read it.

Import existing rulesets
------------------------
The plugin contains a basic import system and 2 basic import filters. These
example import filters are not perfect, use them with care you may loose some
rule data! You can create your own filter (or modify existing ones), if you do
please consider sharing it. To create an import filter you must add a file in
the importFilters directory. The file must contain a class named
'srimport_[filename]'. Each import filter must have:
* An attribute called name - this should be the user friendly name of the
import e.g. Squirrelmail (Avelsieve)
* A pubic function called detector - used to detect of if current rule file
was genereted with the software
* A pubic function called importer - converts the rule file to SieveRules
format

The importer function can return either a string to be parsed by the SieveRules
parser or an array, similar to the one created by the SieveRules parser.

The structure of the rule file
------------------------------
By default this plugin uses \r\n to seperate lines (RFC 5228) if you want to
use \n instead then set
```php
define('RCUBE_SIEVE_NEWLINE', "\n");
```
in your config. Add
```php
define('RCUBE_SIEVE_INDENT', "\t");
```
to change the indent character. By default this plugin places a simple comment
at the top of the rule file to show it was generated by the plugin. This header
can be overridden by setting
```php
define('RCUBE_SIEVE_HEADER', "## Generated by Roundcube SieveRules ##");
```
in your config.

sieverules_connect hook
-----------------------
This hook is triggered right before connecting to the managesieve server and
can be used to change connection details dynamically
Arguments/Return:
* username - (string) the user's username
* password - (string) the user's password
* host - (string) the managesieve host
* port - (int) the managesieve port
* auth_type - (string) authentication type (eg. CRAM-MD5, DIGEST-MD5, PLAIN)
* usetls - (bool) enable TLS for managesieve server connection
* ruleset - (string) the name of the default ruleset file to be used
* dir - (string) the path to the plugin directory, use for locating the
  import filters. This is th path to the directory containing the
  importFilters directory
* elsif - (bool) the rule file can be written as one IF/ELSIF statement or as
  a series of unrelated IF statements
* auth_cid - (string) optional managesieve authentication identifier to be
  used as authorization proxy
* auth_pw - (string)  optional managesieve authentication password to be used
  for sieverules_auth_cid

sieverules_init hook
-----------------------------
This hook allows other plugins to manipulate the default values displayed in
the UI.
Arguments/Return:
* id - (mixed) the id of the rule being viewed
* script - (array) the parameters of the rule being viewed
* extensions - (array) the extensions supported by the Managesieve server
* defaults - (array) collection of default values for selects/lists used in UI

sieverules_load hook
--------------------
Before filter information is loaded into the UI the plugin hook sieverules_load
is executed, this allows you to perform any custom actions like hiding rules
you dont want end users to see.

**NOTE:** rules removed using this hook will need to be added back via the
sieverules_save hook if you want to keep them on the server.
Arguments:
* ruleset - (string) the name of the ruleset file
* script - (string) the raw sieve script

Return:
* script - (string) the raw sieve script

sieverules_save hook
--------------------
Before filter information is saved to the managesieve server the plugin hook
sieverules_save is executed, this allows you to perform any custom actions
like updating another system or performing further validation.
Arguments:
* ruleset - (string) the name of the ruleset file
* script - (string) the raw sieve script

Return:
* script - (string) the raw sieve script
* abort - (boolean) if true the script will not be saved
* message - (string) optional reason why the script was not saved which
  will be shown to the user

[alec]: mailto:alec@alec.pl
[rcplugrepo]: http://plugins.roundcube.net/packages/johndoh/sieverules
[releases]: http://github.com/JohnDoh/Roundcube-Plugin-SieveRules-Managesieve/releases
[rcjqui]: http://github.com/roundcube/roundcubemail/tree/master/plugins/jqueryui
[netsieve]: http://github.com/roundcube/roundcubemail/blob/master/program/lib/Net/Sieve.php
[gpl]: http://www.gnu.org/licenses/gpl.html