<?php

defined('MOODLE_INTERNAL') || die();

// general
$string['modulename'] = 'Talkpoint';
$string['pluginname'] = 'Talkpoint';
$string['modulenameplural'] = 'Talkpoints';
$string['modulename_help'] = 'Allows course participants to comment on and share thoughts on a particular piece of content';
$string['talkpointname'] = 'Talkpoint name';
$string['talkpointclosed_help'] = 'Talkpoints cannot be added to a talkpoint activity that is closed';
$string['invalidtalkpointid'] = 'Talkpoint ID was incorrect';
$string['created'] = 'Created';
$string['modified'] = 'Modified';
$string['closed'] = 'Closed';
$string['header'] = 'Header';
$string['footer'] = 'Footer';
$string['noviewcapability'] = 'You cannot view this talkpoint';
$string['pluginadministration'] = 'Talkpoint administration';
$string['title'] = 'Title';
$string['by'] = 'By';
$string['page'] = 'Page';
$string['of'] = 'Of';
$string['on'] = 'On';
$string['backtotalkpoints'] = 'Back to Talkpoints';
$string['addtextcomment'] = 'Comment with text';
$string['addvideocomment'] = 'Comment with webcam';
$string['addaudiocomment'] = 'Comment with microphone';
$string['yourcomment'] = 'Your comment';
$string['leavefinalfeedback'] = 'Leave this comment as final feedback, closing the talkpoint to further comments';
$string['comments'] = 'Comments';
$string['notalkpoints'] = 'There are no talkpoints in this activity.';
$string['nocomments'] = 'There are no comments in this talkpoint.';
$string['commentfrom'] = 'Comment from';
$string['activityclosed:user'] = 'This activity is closed. No talkpoints can be added.';
$string['talkpointclosed:user'] = 'This talkpoint is closed. No comments can be added.';
$string['activityclosed:admin'] = 'This activity is closed. No talkpoints can be added by students.';
$string['talkpointclosed:admin'] = 'This talkpoint is closed. No comments can be added by students.';
$string['webcam'] = 'Webcam';
$string['audio'] = 'Audio';
$string['addwebcamvideo'] = 'Add webcam video';
$string['nomediatoshow'] = 'There is no media to show';
$string['bothmedianotallowed'] = 'You should either upload a file or a webcam video, not both.';
$string['confirm'] = 'Are you sure?';
$string['file:confirmlose'] = 'The file you uploaded will be lost. Continue anyway?';
$string['submit'] = 'You must upload a file or record a webcam video!';
$string['webcam:showcurrent'] = 'View current recording';
$string['webcam:recordnew'] = 'Record new video';
$string['webcam:saved'] = 'Your video has been saved. Thank you!';
$string['webcam:confirmlose'] = 'The webcam you recorded will be lost. Continue anyway?';
$string['audio:showcurrent'] = 'Listen to current recording';
$string['audio:recordnew'] = 'Record new audio';
$string['audio:saved'] = 'Your audio has been saved. Thank you!';
$string['audio:confirmlose'] = 'The audio you recorded will be lost. Continue anyway?';
$string['savewebcam'] = 'Save webcam';
$string['saveaudio'] = 'Save audio';
$string['videostoconvert1'] = 'This talkpoint will not be visible (except to the user who created it) until its video has been converted into a compatible format.';
$string['videostoconvert2'] = 'This will usually happen within five minutes of the video being uploaded.';
$string['adding'] = 'Adding';

// capabilities
$string['talkpoint:addinstance'] = 'Add a new talkpoint';
$string['talkpoint:view'] = 'View a talkpoint';

// jPlayer controls
$string['jplayer:play'] = 'Play';
$string['jplayer:pause'] = 'Pause';
$string['jplayer:stop'] = 'Stop';
$string['jplayer:mute'] = 'Mute';
$string['jplayer:unmute'] = 'Unmute';
$string['jplayer:maxvolume'] = 'Max volume';
$string['jplayer:fullscreen'] = 'Full screen';
$string['jplayer:restorescreen'] = 'Restore screen';
$string['jplayer:repeat'] = 'Repeat';
$string['jplayer:repeatoff'] = 'Repeat off';

// JSON API
$string['jsonapi:commentmissing'] = 'Either text or nimbbguid comment must be provided';
$string['jsonapi:commentambiguous'] = 'Both text and nimbbguid comments cannot be provided';
$string['jsonapi:talkpointclosed'] = 'This talkpoint is closed and no longer accepts comments';
$string['jsonapi:commentasguestdenied'] = 'You cannot comment on a talkpoint as the guest user';
$string['jsonapi:notowneroftalkpoint'] = 'You are not the owner of this talkpoint';
$string['jsonapi:notownerofcomment'] = 'You are not the owner of this comment';
$string['jsonapi:clicktoplaycomment'] = 'Click to play comment made by {$a}';
