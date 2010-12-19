=== x7Host's Videox7 UGC Plugin ===
Contributors: rtcwp07, Kaltura
Donate link: http://www.kalturecehost.com/
Tags: plugin, admin, images, posts, Post, comments, kaltura, participate, media library, edit, camera, podcast, record, vlog, video editor, video responses, video blog, audio, media, flickr, Facebook, mix, mixing, remix, collaboration, interactive, richmedia cms, webcam, ria, CCMixter, Jamendo, rich-media, picture, editor, player, video comments, New York Public Library, photo, video, all in one, playlist, video gallery, gallery, widget, all-in-one, transcoding, encoding, advertising, video ads, video advertising
Requires at least: 3.0
Tested up to: 3.0.3
Stable tag: 2.5.0

Easily add full video capabilities to your blog.

== Description ==

This plugin is a fork of Kaltura's original "All In One Video Pack" plugin, enhanced in many ways and designed from the bottom up to be easily integrated with the self-hosted Kaltura Community Edition.

This is not just another video embed tool - it includes every functionality you might need for video and rich-media, including the ability to upload/ record/import videos directly to your post, edit and remix content with both a standard and advanced video editor, enable video and webcam comments, manage and track your video content, create and edit playlists and much more.

Highlights:

* Give your logged in Wordpress users the ability to upload/edit/remix/share/post their media on your blog!  This is true user generated content!
* Upload, record from webcam and import all rich-media directly to your blog post; 
* Edit and remix videos using Kaltura's online full-featured video editor; 
* Easily import all rich media (video, audio, pictures...) from other sites and social networks, such as Flickr, CCMixter, Jamendo, New York Public Library, any URL on the web etc.; 
* Allow readers and subscribers to add video and audio comments, and to participate in collaborative videos; 
* Manage and track interactive videos through the management console; 
* Enable video advertising
* Sidebar widget displaying thumbnails of recent videos and video comments
* Complete administrative capabilities. You decide who can add and edit each video; 
* Supports more than 150 video, audio and image file formats and codecs 
* Choose your preferred video player style for each player you embed
* Custom sizing of the video player 
* Update thumbnail of video by selecting frame from video
* Advanced sharing options for videos 
* Sidebar widget showing all recent videos posted and video comments.
* Easy installation that takes just 4 steps and a few minutes. 


Version 2.5.0
-------------
* First fork from All In One and merge with Videox7

Showcase your blog, see examples and pictures of the plugin and get support in our forum: http://www.kalturacehost.com


Kaltura Hosted Solution - Free Trial and Affordable Packages
-------------
You still have the option to hook this plugin up to the Kaltura.com SaaS if you like.  Or you can get your own KalturaCE cloud video server from x7Host...

Self-Hosted Solution
-------------
With a cloud video server from x7Host (www.x7host.com), you can get your own enterprise class online video platform on your own server in the clouds, that grows and shrinks as you need it to.  Whether you have a small personal video blog or hundreds of thousands of video viewers a month, x7Host can give you the cloud video server you need.  This plugin is easily integrated with the x7Host cloud servers.

== Installation ==

If you are installing this plugin for the first time:

1. Download and extract the plugin zip file to your local machine
2. Paste the 'all-in-one-video-pack' directory under the '/wp-content/plugins/' directory
3. Activate the plugin through the 'Plugins' menu in the WordPress admin application
4. Go to Settings > All in One Video Pack to setup the plugin
5. Enter additional configuration settings in the 'x7 UGC Settings' page
6. IMPORTANT! If you are using your own KalturaCE server, the very first thing you must do is edit the file "settings.php" and enter in your server URL for the variables "KALTURA_SERVER_URL" and "KALTURA_CDN_URL"!  Please don't forget to do this!

If you are upgrading your current version of the plugin, or if you're upgrading from the Interactive Video plugin: 

1. Deactivate the plugin through the 'Plugins' menu in the WordPress admin application
2. Download the latest version
3. Follow the installation steps above

Installing the Recent Videos Sidebar Widget

1. Activate the All in One Video Pack Sidebar Widget through the 'Plugins' menu in the WordPress admin application
2. Go to Design > Widgets in the WordPress admin application, then click Add to add the Recent Videos Widget to your sidebar 

Note that videos from earlier versions of the plugin will not show up on the sidebar unless they are reposted, or you can edit them with the Kaltura video editor, resave them, and they will appear in the sidebar.

== Frequently Asked Questions ==

= I installed the plugin, but installation failed after pressing Complete Installation, showing me a text in a red rectangle? =

Cause: Either curl / curl functions is disabled on your server or your hosting blocks API calls to the Kaltura servers.

Solution 1: Enable curl and its functions on the server (or have the hosting company enable it for you).

Solution 2: Remove any blocking of external calls from the server.

= I can't activate the plugin, it presents an error message after clicking Activate on the plugin list =
It might be caused due to an old version of PHP.

This plugin is written for PHP4 and PHP5 with the use of classes and static members, these are not supported on earlier versions of PHP.

== Screenshots ==

1. Blog main page with video posts
2. Add a video comment
3. Add Video Screen
4. Entries Library
5. Player with interactive options of adding assets (photo, video, audio) to the video and edit
6. Create Video Posts
7. The plugin settings page
8. Video Editor