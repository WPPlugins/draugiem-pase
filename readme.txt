=== Draugiem.lv Pase ===
Contributors: girts_u
Tags: authentication, registration, social networking, draugiem, login
Requires at least: 2.7
Tested up to: 2.9.1
Stable tag: 1.0
Donate link: -

Provides authentication for WordPress with "Draugiem pase" authentication method provided by draugiem.lv social network.

== Description ==

Allows to register, log in and post comments to a WordPress based website by using "Draugiem pase" authentication provided by draugiem.lv social network.
To use this plugin, you have to get your App ID and API key by registering your application on draugiem.lv - [development section](http://www.draugiem.lv/development/?view=my_dev_apps).
Plugin replaces user avatars with their draugiem.lv profile pics (may not work with older themes) and shows a link to draugiem.lv profile next to the comments.

== Installation ==

1. Upload folder `/draugiem-pase/` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Create your application in [draugiem.lv development section](http://www.draugiem.lv/development/?view=my_dev_apps) to get your App ID and API key.
4. Configure your App ID and API key in 'Settings'->'Draugiem pase' menu in WordPress.
5. That's all.

== Frequently Asked Questions ==

= How will this affect my WordPress installation? =

One new MySQL table will be created created - `{wp db prefix}draugiem_users` - it will contain extra information about draugiem.lv users that register in your site. Every user that logs in will get his own entry in WordPress user table - without administration access, but you can later give them full access to your Wordpress administration.

= Profile pictures next to the comments are not visible - what should I do? =

Usually this problem is caused by old or improperly designed WordPress themes. Update your theme or get a better one.

= How often user profile data will be updated? =

Plugin fetches profile information for every user once a day, by executing WordPress hourly cron jobs. Userdata will be also updated when a user logs in. Updated profile name and image will be visible next to all the comments that are made by this user. If a user data can no longer be accessed through API, comments made by this user will be displayed as normal comments.

== Screenshots ==

1. Draugiem pase configuration panel
2. Login button under the comment form
3. Comment that is posted by authenticated user

== Changelog ==

= 1.0 =
* First release

== Upgrade Notice ==

= 1.0 =
First release