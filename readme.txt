=== Event Calendar / Scheduler ===
Contributors: dhtmlx
Donate link: http://www.dhtmlx.com/docs/contact.shtml
Tags: calendar, events, scheduler, ajax, javascript, plugin, date, archive
Requires at least: 2.0.2
Tested up to: 3.0
Stable tag: 2.0

An Ajax-based calendar plugin which can be used as a scheduler to visualize and manage events/appointments, or as a blog archive calendar.  

== Description ==

An easy to implement event calendar plugin built on top of <a href="http://dhtmlx.com/docs/products/dhtmlxScheduler/">dhtmlxScheduler</a>, which provides Ajax-based scheduling solution similar to Microsoft Outlook Calendar, Apple's iCal or Google Calendar. The plugin allows you to manage single or multiple user events through easy and intuitive dynamic interface. Users can add/modify/delete events on the fly and easily change events dates and time by simply dragging the event boxes. You can set up different levels of permissions to people who will use the calendar. 

The scheduler can be configured to display events in Day, Week, or Month view, as well as in any custom view. If there is a need to display recurring events, users can create events which will be repeated on daily, weekly, monthly or yearly basis. 

You can use the scheduler as an ordinary calendar on a webpage to visualize some events/appointments, or as a calendar to display your blog posts archive (in this case it works in read-only mode). 

The main features include:

- Day/Week/Month/Year/Agenda view + ability to create custom view 
- Drag-n-drop support to configure event date and time
- Customizable appearance 
- Single/multi-days events (daily, weekly, monthly or yearly basis)
- Customizable time scale 
- Recurring events 
- Multilingual

Requirements

- PHP 5.0 or greater
- MySQL 4.0 or greater

== Installation ==

1. Upload the content of event-calendar-scheduler.zip to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

After plugin activation, you will have a new page in your blog, with calendar on it. 
You can configure it through Plugins menu.

**SideBar installation**

To have the list of oncoming events on the sidebar, you can add the next line into sidebar's template 
<code>
 <?php if (function_exists('scheduler_sidebar')) echo scheduler_sidebar(); ?>
</code>

If you are using Widget-capable theme, "Upcoming Events" widget can be used for the same.
 
**Export to iCal format**

To add such possibility, just add the next link somewhere on the page ( inside post, or inside sidebar's template )

<code>
<a href='./wp-content/plugins/event-calendar-scheduler/ical.php'>Export events</a>
</code>

If you need to export only oncoming events, the link will look as 
<code>
<a href='./wp-content/plugins/event-calendar-scheduler/ical.php?oncoming'>Export oncoming events</a>
</code>

== Frequently Asked Questions ==

= The scheduler is distorted, it doesn't look good. =

The scheduler was tested with most popular themes for Wordpress, but still it’s possible that theme used in your case is not compatible with the scheduler's styles. 
Please drop an email to   dhtmlx [at] gmail [dot] com   with the name of used theme.

= Scheduler throws "Incorrect XML" error  =

Most probably you are using php 4.x , which is not supported.
In settings of plugin enable "Debug mode" and check the problematic page again - now it must contain more readable problem description. 

= How to change the scheduler's style =

+ Go to the [http://dhtmlx.com/docs/products/dhtmlxScheduler/skinBuilder/index.shtml](http://dhtmlx.com/docs/products/dhtmlxScheduler/skinBuilder/index.shtml)
+ Create and download custom skin pack
+ Unzip skin pack to the wp-content\plugins\event-calendar-scheduler\codebase 
+ Because skin can be reset by future updates, it has sense to store skin-pack somewhere for later usage as well

= How I can edit|delete events =

All operations can be done through the public GUI
[documentation](http://dhtmlx.com/dhxdocs/doku.php?id=dhtmlxscheduler:external_plugin:wordpress)

Also, you can create new events during post creating | editing
[documentation](http://dhtmlx.com/dhxdocs/doku.php?id=dhtmlxscheduler:external_plugin:wordpress#post_creating_form)

= I still not able to create new event =

Check the settings of scheduler, the "Add" action must be enabled for the related user group, to be able to add the new event. 

= I have a question - ... = 
If something is still not clear - you can ask your question at [dhtmlx support forum](http://forum.dhtmlx.com/viewforum.php?f=6)

== Screenshots ==

1. Events calendar within a blog page 
2. A new event window
3. Admin panel

== Changelog ==

= 1.0 =
Initial release.

= 1.1 =
+ improved compatibility with themes of Wordpress
+ rights management is extended
+ agenda view is added
- problem with events in non-latin encoding is fixed
- problem with absolute paths is fixed

= 1.2 =
+ details are shown in readonly mode if user has "view" access
+ ability to export data in ical format
+ ability to place list of oncoming events on sidebar
+ ability to create direct links to specific date is added
+ multi-day events can be rendered on daily and weekly view
* calendar widget is added to the "new event" form
- problem with quotes in event's text is fixed

= 1.2.1 =
- hotfix for path on linux based installations

= 1.3 =
+ year view
+ custom skins
+ recurring events related fixes
+ js code updated to the dhtmlxScheduler 2.1
+ WordPress MU compatibility 
+ client side localization for 13 languages

= 2.0 =
+ new admin panel
+ optional mini-calendar navigation 
+ units view 
+ configurable time|text templates
+ backend GUI for events management
+ ability to define custom fields
+ ability to define event's color
+ new skin 
+ codebase updated to dhtmlxScheduler 2.2
+ compatible with WP 3.x
