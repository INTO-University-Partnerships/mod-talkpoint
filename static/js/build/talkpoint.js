(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
'use strict';

var app = angular.module('talkpointsApp', ['talkpointsApp.controllers', 'talkpointsApp.services', 'talkpointsApp.directives', 'ngSanitize']);

app.config(['$sceDelegateProvider', function ($sceDelegateProvider) {
    $sceDelegateProvider.resourceUrlWhitelist(['self', 'http://player.nimbb.com/**', 'https://player.nimbb.com/**', 'http://api.nimbb.com/**', 'https://api.nimbb.com/**']);
}]);

/*
app.config(['$sceProvider', function ($sceProvider) {
    $sceProvider.enabled(false);
}]);
*/

app.constant('NIMBB_PUBLIC_KEY', 'c7d8097faa');
app.constant('NIMBB_SWF_URL', 'https://player.nimbb.com/nimbb.swf');

app.constant('CONFIG', window.CONFIG);
delete window.CONFIG;

},{}],2:[function(require,module,exports){
'use strict';

require('./angular-app');

require('./controllers');

require('./directives');

require('./services');

},{"./angular-app":1,"./controllers":3,"./directives":4,"./services":5}],3:[function(require,module,exports){
'use strict';

var app = angular.module('talkpointsApp.controllers', []);

app.controller('talkpointCtrl', ['$scope', '$timeout', '$window', 'talkpointsSrv', 'commentsSrv', 'CONFIG', function ($scope, $timeout, $window, talkpointsSrv, commentsSrv, config) {
    $scope.talkpoint = null;
    $scope.comments = null;
    $scope.commentsExtra = {};
    $scope.total = 0;
    $scope.perPage = 5;
    $scope.currentPage = 0;
    $scope.canManage = config.canManage;
    $scope.isGuest = config.isGuest;
    $scope.talkpointClosed = config.talkpointClosed;
    $scope.timeoutPromise = null;
    $scope.nimbbControl = {};
    $scope.isNimbbVideoPlaying = false;

    talkpointsSrv.getTalkpoint(config.talkpointid).then(function (data) {
        $scope.talkpoint = data;
    });

    $scope.prevPage = function () {
        if ($scope.currentPage > 0) {
            --$scope.currentPage;
        }
    };

    $scope.prevPageDisabled = function () {
        var disabled = $scope.currentPage === 0 ? 'disabled' : '';
        if (!disabled) {
            disabled = $scope.isNimbbVideoPlaying ? 'disabled' : '';
        }
        return disabled;
    };

    $scope.nextPage = function () {
        if ($scope.currentPage < $scope.pageCount() - 1) {
            $scope.currentPage++;
        }
    };

    $scope.nextPageDisabled = function () {
        var disabled = $scope.currentPage === $scope.pageCount() - 1 ? 'disabled' : '';
        if (!disabled) {
            disabled = $scope.isNimbbVideoPlaying ? 'disabled' : '';
        }
        return disabled;
    };

    $scope.pageCount = function () {
        if ($scope.total === 0) {
            return 1;
        }
        return Math.ceil($scope.total / $scope.perPage);
    };

    $scope.getPageOfComments = function (currentPage) {
        $timeout.cancel($scope.timeoutPromise);
        commentsSrv.getPageOfComments(currentPage, $scope.perPage).then(function (data) {
            $scope.comments = data.comments;
            $scope.total = data.total;
            $scope.talkpointClosed = data.talkpointClosed;
            angular.forEach($scope.comments, function (comment) {
                if (angular.isDefined($scope.commentsExtra[comment.id])) {
                    if ($scope.commentsExtra[comment.id].speechBubble) {
                        $scope.commentsExtra[comment.id].speechBubble = comment.textcomment;
                    }
                }
            });
            $scope.timeoutPromise = $timeout(function () {
                $scope.getPageOfComments($scope.currentPage);
            }, 10000);
        });
    };

    $scope.$watch('currentPage', function (newValue) {
        $scope.getPageOfComments(newValue);
    });

    $scope.postTextComment = function (textcomment, finalfeedback) {
        $scope.textcomment = '';
        $scope.talkpointClosed = finalfeedback;
        commentsSrv.postTextComment(textcomment, finalfeedback).then(function () {
            $scope.getPageOfComments($scope.currentPage);
        });
    };

    $scope.postNimbbComment = function (guid, finalfeedback) {
        $scope.talkpointClosed = finalfeedback;
        commentsSrv.postNimbbComment(guid, finalfeedback).then(function () {
            $scope.getPageOfComments($scope.currentPage);
        });
    };

    $scope.cancelChanges = function () {
        // empty
    };

    $scope.startEdit = function (comment) {
        if (!angular.isDefined($scope.commentsExtra[comment.id])) {
            $scope.commentsExtra[comment.id] = {};
        }
        $scope.commentsExtra[comment.id].textcomment = comment.textcomment;
        $scope.commentsExtra[comment.id].editing = true;
    };

    $scope.stopEdit = function (comment) {
        if (!angular.isDefined($scope.commentsExtra[comment.id])) {
            $scope.commentsExtra[comment.id] = {};
        }
        $scope.commentsExtra[comment.id].textcomment = '';
        $scope.commentsExtra[comment.id].editing = false;
    };

    $scope.putTextComment = function (comment) {
        var textcomment = $scope.commentsExtra[comment.id].textcomment;
        $scope.commentsExtra[comment.id].textcomment = '';
        $scope.commentsExtra[comment.id].editing = false;
        commentsSrv.putTextComment(comment.id, textcomment).then(function (data) {
            $scope.getPageOfComments($scope.currentPage);
            if (angular.isDefined($scope.commentsExtra[comment.id]) && $scope.commentsExtra[comment.id].speechBubble) {
                $scope.commentsExtra[comment.id].speechBubble = data.textcomment;
            }
        });
    };

    $scope.deleteComment = function (commentid) {
        if (!$window.confirm(config.messages.confirm)) {
            return;
        }
        commentsSrv.deleteComment(commentid).then(function () {
            $scope.getPageOfComments($scope.currentPage);
            if (angular.isDefined($scope.commentsExtra[commentid])) {
                delete $scope.commentsExtra[commentid];
            }
        });
    };

    $scope.backToTalkpoints = function () {
        $window.location.href = config.baseurl + '/' + config.instanceid;
    };

    $scope.openComment = function (comment) {
        if (!angular.isDefined($scope.commentsExtra[comment.id])) {
            $scope.commentsExtra[comment.id] = {};
        }

        // close the given comment
        if ($scope.closeComment(comment.id)) {
            $scope.getPageOfComments($scope.currentPage);
            return;
        }

        // close all other comments
        angular.forEach($scope.commentsExtra, function (commentExtra, index) {
            if (index !== comment.id) {
                $scope.closeComment(index);
            }
        });

        // open the given comment
        if (comment.nimbbguidcomment) {
            $scope.isNimbbVideoPlaying = true;
            $timeout.cancel($scope.timeoutPromise);
            $scope.commentsExtra[comment.id].nimbbGuid = comment.nimbbguidcomment;
        } else if (comment.textcomment) {
            $scope.commentsExtra[comment.id].speechBubble = comment.textcomment;
        }
    };

    $scope.closeComment = function (commentid, digest) {
        if (!angular.isDefined($scope.commentsExtra[commentid])) {
            return false;
        }
        if ($scope.commentsExtra[commentid].nimbbGuid) {
            $scope.isNimbbVideoPlaying = false;
            $scope.commentsExtra[commentid].nimbbGuid = '';
            if (digest) {
                $scope.$digest();
                $scope.getPageOfComments($scope.currentPage);
            }
            return true;
        }
        if ($scope.commentsExtra[commentid].speechBubble) {
            $scope.commentsExtra[commentid].speechBubble = '';
            return true;
        }
        return false;
    };

    $window.Nimbb_initCompleted = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_initCompleted)) {
            return;
        }
        var f = 'Nimbb_initCompleted';
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_recordingStopped = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_recordingStopped)) {
            return;
        }
        var f = 'Nimbb_recordingStopped';
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_videoSaved = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_videoSaved)) {
            return;
        }
        var f = 'Nimbb_videoSaved';
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_stateChanged = function (idPlayer, state) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_stateChanged)) {
            return;
        }
        var f = 'Nimbb_stateChanged';
        $scope.nimbbControl[idPlayer][f](idPlayer, state);
    };

    $window.Nimbb_modeChanged = function (idPlayer, mode) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_modeChanged)) {
            return;
        }
        var f = 'Nimbb_modeChanged';
        $scope.nimbbControl[idPlayer][f](idPlayer, mode);
    };

    $window.Nimbb_playbackStopped = function (idPlayer, endReached) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_playbackStopped)) {
            return;
        }
        // workaround an apparent bug in Nimbb whereby 'endReached' is coming through as an array
        if (angular.isArray(endReached)) {
            endReached = endReached[0];
        }
        var f = 'Nimbb_playbackStopped';
        $scope.nimbbControl[idPlayer][f](idPlayer, endReached);
    };
}]);

app.controller('talkpointsCtrl', ['$scope', '$timeout', '$window', 'talkpointsSrv', 'CONFIG', function ($scope, $timeout, $window, talkpointsSrv, config) {
    $scope.talkpoints = null;
    $scope.total = 0;
    $scope.perPage = 5;
    $scope.currentPage = 0;
    $scope.baseurl = config.baseurl;
    $scope.canManage = config.canManage;
    $scope.isGuest = config.isGuest;
    $scope.closed = config.closed;
    $scope.timeoutPromise = null;

    $scope.prevPage = function () {
        if ($scope.currentPage > 0) {
            --$scope.currentPage;
        }
    };

    $scope.prevPageDisabled = function () {
        return $scope.currentPage === 0 ? 'disabled' : '';
    };

    $scope.nextPage = function () {
        if ($scope.currentPage < $scope.pageCount() - 1) {
            $scope.currentPage++;
        }
    };

    $scope.nextPageDisabled = function () {
        return $scope.currentPage === $scope.pageCount() - 1 ? 'disabled' : '';
    };

    $scope.pageCount = function () {
        if ($scope.total === 0) {
            return 1;
        }
        return Math.ceil($scope.total / $scope.perPage);
    };

    $scope.getPageOfTalkpoints = function (currentPage) {
        $timeout.cancel($scope.timeoutPromise);
        talkpointsSrv.getPageOfTalkpoints(currentPage, $scope.perPage).then(function (data) {
            $scope.talkpoints = data.talkpoints;
            $scope.total = data.total;
            $scope.timeoutPromise = $timeout(function () {
                $scope.getPageOfTalkpoints($scope.currentPage);
            }, 10000);
        });
    };

    $scope.$watch('currentPage', function (newValue) {
        $scope.getPageOfTalkpoints(newValue);
    });

    $scope.addTalkpoint = function () {
        $window.location.href = config.baseurl + '/' + config.instanceid + '/add';
    };

    $scope.editTalkpoint = function (talkpointid) {
        $window.location.href = config.baseurl + '/' + config.instanceid + '/edit/' + talkpointid;
    };

    $scope.deleteTalkpoint = function (talkpointid) {
        if (!$window.confirm(config.messages.confirm)) {
            return;
        }
        talkpointsSrv.deleteTalkpoint(talkpointid).then(function () {
            $scope.getPageOfTalkpoints($scope.currentPage);
        });
    };
}]);

app.controller('talkpointsAddEditCtrl', ['$scope', '$window', 'CONFIG', function ($scope, $window, config) {
    $scope.nimbbControl = {};
    $scope.nimbbguid = config.nimbbguid;
    $scope.mediaType = config.mediaType;
    $scope.uploadedfile = config.uploadedfile;
    $scope.showCurrentRecording = !!$scope.nimbbguid;

    $scope.setButtonLabel = function () {
        if ($scope.mediaType === 'webcam' || $scope.mediaType === 'audio') {
            $scope.toggleButtonLabel = config.messages[$scope.mediaType + ($scope.showCurrentRecording ? ':recordnew' : ':showcurrent')];
        }
    };
    $scope.setButtonLabel();

    $scope.saveMedia = function (guid) {
        $scope.nimbbguid = guid;
        $scope.$broadcast('nimbbchanged', $scope.nimbbguid);
        $scope.$digest();
    };

    $scope.cancelChanges = function () {
        $scope.changeMediaType('');
    };

    $scope.toggleCurrentRecording = function () {
        $scope.showCurrentRecording = !$scope.showCurrentRecording;
        $scope.setButtonLabel();
    };

    $scope.showConfirmationMessageBeforeChangingMediaType = function () {
        // adding a new talkpoint (config.mediaType is empty)
        if (!config.mediaType) {
            // show a confirmation if a file has been uploaded or a webcam or audio has been recorded
            return !!$scope.uploadedfile || !!$scope.nimbbguid;
        }

        // editing an existing talkpoint (currently on the 'file' media type)
        if ($scope.mediaType === 'file') {
            if (config.mediaType === 'file') {
                return !!$scope.uploadedfile && $scope.uploadedfile !== config.uploadedfile;
            }
            return !!$scope.uploadedfile;
        }

        // editing an existing talkpoint (currently on the 'webcam' media type)
        if ($scope.mediaType === 'webcam') {
            if (config.mediaType === 'webcam') {
                return !!$scope.nimbbguid && $scope.nimbbguid !== config.nimbbguid;
            }
            return !!$scope.nimbbguid;
        }

        // editing an existing talkpoint (currently on the 'audio' media type)
        if ($scope.mediaType === 'audio') {
            if (config.mediaType === 'audio') {
                return !!$scope.nimbbguid && $scope.nimbbguid !== config.nimbbguid;
            }
            return !!$scope.nimbbguid;
        }

        // nothing has been uploaded or recorded that'll be lost when changing media type
        return false;
    };

    $scope.changeMediaType = function (type) {
        var showConf = $scope.showConfirmationMessageBeforeChangingMediaType();
        if (!(showConf === true || showConf === false)) {
            throw new Error('expected a boolean');
        }
        if (showConf) {
            if (!$window.confirm(config.messages[$scope.mediaType + ':confirmlose'])) {
                return false;
            }
        }
        $scope.nimbbguid = '';
        $scope.uploadedfile = '';
        $scope.mediaType = type;
        $scope.showCurrentRecording = false;
        $scope.setButtonLabel();
    };

    $window.Nimbb_initCompleted = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_initCompleted)) {
            return;
        }
        var f = 'Nimbb_initCompleted';
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_recordingStopped = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_recordingStopped)) {
            return;
        }
        var f = 'Nimbb_recordingStopped';
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_videoSaved = function (idPlayer) {
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_videoSaved)) {
            return;
        }
        var f = ['Nimbb_videoSaved'];
        $scope.nimbbControl[idPlayer][f](idPlayer);
    };

    $window.Nimbb_stateChanged = function (idPlayer, state) {
        if (angular.isArray(state)) {
            state = state[0];
        }
        if (!angular.isDefined($scope.nimbbControl[idPlayer]) || !angular.isDefined($scope.nimbbControl[idPlayer].Nimbb_stateChanged)) {
            return;
        }
        var f = 'Nimbb_stateChanged';
        $scope.nimbbControl[idPlayer][f](idPlayer, state);
        $scope.toggleButtonDisabled = state === 'recording';
        $scope.$digest();
    };
}]);

},{}],4:[function(require,module,exports){
'use strict';

var app = angular.module('talkpointsApp.directives', []);

app.directive('talkpointListItem', ['CONFIG', function (config) {
    return {
        restrict: 'E',
        scope: {
            talkpoint: '=',
            baseurl: '@',
            canManage: '@',
            editT: '&',
            deleteT: '&'
        },
        templateUrl: config.baseurl + '/partials/talkpointListItem.twig'
    };
}]);

app.directive('jplayer', ['CONFIG', function (config) {
    return {
        restrict: 'E',
        scope: {
            title: '@'
        },
        link: function link(scope, element, attrs) {
            var jplayer = angular.element('#jquery_jplayer_1');
            jplayer.jPlayer({
                ready: function ready() {
                    var obj = {};
                    angular.forEach(attrs.formats.split(','), function (format) {
                        if (['m4v', 'ogv', 'webmv'].indexOf(format) !== -1) {
                            obj[format] = config.baseurl + '/servevideofile/' + attrs.talkpointid + '/' + format;
                        } else {
                            obj[format] = config.baseurl + '/servefile/' + attrs.talkpointid;
                        }
                    });
                    jplayer.jPlayer('setMedia', obj);
                    jplayer.jPlayer('play');
                },
                swfPath: config.baseurl + '/js/jplayer',
                supplied: attrs.formats,
                solution: 'html, flash',
                preload: 'auto',
                error: function error(event) {
                    if (event.jPlayer.error.type === 'e_no_solution') {
                        var errorMsg = 'This video format is not supported by your browser.';
                        angular.element('.jp-no-solution').html(errorMsg);
                    }
                }
            });
        },
        templateUrl: config.baseurl + '/partials/jplayer.twig'
    };
}]);

app.directive('addTextComment', ['CONFIG', function (config) {
    return {
        restrict: 'E',
        scope: {
            addingTextComment: '=',
            saveChanges: '&'
        },
        link: function link(scope, element, attrs) {
            attrs.$observe('canManage', function (value) {
                scope.canManage = scope.$eval(value);
            });
            attrs.$observe('talkpointClosed', function (value) {
                scope.talkpointClosed = scope.$eval(value);
            });
            scope.textcomment = '';
        },
        templateUrl: config.baseurl + '/partials/addTextComment.twig'
    };
}]);

app.directive('addMediaComment', ['NIMBB_SWF_URL', 'NIMBB_PUBLIC_KEY', 'CONFIG', function (swfUrl, publicKey, config) {
    return {
        restrict: 'E',
        scope: {
            addingMediaComment: '=',
            saveChanges: '&',
            cancelChanges: '&',
            nimbbControl: '=',
            mediaType: '='
        },
        link: function link(scope, element, attrs) {
            attrs.$observe('canManage', function (value) {
                scope.canManage = scope.$eval(value);
            });
            attrs.$observe('talkpointClosed', function (value) {
                scope.talkpointClosed = scope.$eval(value);
            });
            scope.toggleButtonLabel = '...';
            scope.toggleButtonDisabled = true;
            scope.readyToSave = false;
            scope.unique = 'nimbb';
            scope.nimbbControl[scope.unique] = {};
            scope.templateInit = swfUrl + '?mode=record' + '&key=' + publicKey + '&nologo=1&lang=en&simplepage=1&showmenu=0&showcounter=0';

            scope.$watch('mediaType', function (value) {
                // add 'disablecamera=1' in case of commenting with microphone only.
                scope.init = scope.templateInit;
                scope.init += value === 'audio' ? '&disablecamera=1' : '';
                // add a custom 'thank you' message
                if ((value === 'audio' || value === 'webcam') && config.messages[value + ':saved']) {
                    scope.init += '&message=' + encodeURIComponent(config.messages[value + ':saved']);
                }

                // clone the '<object>', replace URLs and replace the original '<object>'
                // with the clone in order for the SWF to pick up the correct URL.
                var clone = element.find('object').clone();
                clone.find('embed').attr('src', scope.init);
                clone.find('param')[0].value = scope.init;
                element.find('object').replaceWith(clone);
            });

            scope.nimbbControl[scope.unique].Nimbb_initCompleted = function () {
                scope.nimbb = document[scope.unique];
                scope.toggleButtonLabel = 'Start recording';
                scope.toggleButtonDisabled = false;
                scope.$digest();
            };

            scope.nimbbControl[scope.unique].Nimbb_stateChanged = function (idPlayer, state) {
                if (angular.isArray(state)) {
                    state = state[0];
                }
                if (state === 'ready') {
                    scope.toggleButtonLabel = 'Start recording';
                    scope.toggleButtonDisabled = false;
                } else if (state === 'recording') {
                    scope.toggleButtonLabel = 'Stop recording';
                    scope.toggleButtonDisabled = false;
                } else {
                    scope.toggleButtonLabel = '...';
                    scope.toggleButtonDisabled = true;
                }
                scope.$digest();
            };

            scope.nimbbControl[scope.unique].Nimbb_videoSaved = function () {
                scope.saveChanges({
                    guid: scope.nimbb.getGuid(),
                    finalfeedback: scope.finalfeedback
                });
                scope.cancel();
            };

            scope.nimbbControl[scope.unique].Nimbb_recordingStopped = function () {
                if (scope.nimbb.isReadOnly()) {
                    return;
                }
                scope.readyToSave = true;
                scope.$digest();
            };

            scope.toggleRecording = function () {
                var state = scope.nimbb.getState();
                if (angular.isArray(state)) {
                    state = state[0];
                }
                if (state === 'ready') {
                    scope.nimbb.recordVideo();
                } else if (state === 'recording') {
                    scope.nimbb.stopVideo();
                }
            };

            scope.saveMedia = function () {
                if (scope.nimbb.isReadOnly()) {
                    scope.cancel();
                    return;
                }
                scope.readyToSave = false;
                scope.nimbb.saveVideo();
            };

            scope.cancel = function () {
                var state = scope.nimbb.getState();
                if (angular.isArray(state)) {
                    state = state[0];
                }
                if (state === 'recording') {
                    scope.nimbb.stopVideo();
                }
                scope.toggleButtonLabel = '...';
                scope.toggleButtonDisabled = true;
                scope.readyToSave = false;
                scope.addingMediaComment = false;
            };

            scope.shutdown = function () {
                scope.cancel();
                scope.cancelChanges();
            };
        },
        templateUrl: config.baseurl + '/partials/addMediaComment.twig'
    };
}]);

app.directive('playNimbbVideo', ['NIMBB_SWF_URL', 'NIMBB_PUBLIC_KEY', 'CONFIG', '$compile', function (swfUrl, publicKey, config, $compile) {

    /**
     * linking function for the Flash-based Nimbb player
     * @param {object} scope
     * @param {object} element
     */
    var flashPlayerLinkingFunction = function flashPlayerLinkingFunction(scope, element) {
        if (!scope.nimbbGuid) {
            return;
        }
        scope.playerType = 'flash';

        scope.unique = 'nimbb_' + scope.nimbbGuid;
        scope.nimbbControl[scope.unique] = {};
        scope.init = swfUrl + '?guid=' + scope.nimbbGuid + '&key=' + publicKey + '&autoplay=true&nologo=1&lang=en&simplepage=1&showmenu=1&showcounter=0&bgcolor=000000';

        scope.recompile = function () {
            // clone the '<object>', replace URLs and replace the original '<object>'
            // with the clone in order for the SWF to pick up the correct URL.
            var clone = element.find('object').clone();
            clone.find('embed').attr({
                'src': scope.init,
                'ng-src': scope.init
            });
            clone.find('param')[0].value = scope.init;
            $compile(clone)(scope);
            element.find('object').replaceWith(clone);
        };

        // only if the video is the subject of the Talkpoint (i.e. is not a comment)
        if (scope.isSubject) {
            setTimeout(function () {
                scope.recompile();
            }, 100);
        }

        scope.$on('nimbbchanged', function (event, nimbbguid) {
            scope.nimbbGuid = nimbbguid;
            scope.init = scope.init.replace(/guid=[a-z0-9]+/, 'guid=' + nimbbguid);
            scope.recompile();
            scope.$digest();
        });

        scope.nimbbControl[scope.unique].Nimbb_playbackStopped = function (idPlayer, endReached) {
            if (typeof scope.commentid !== 'undefined' && endReached) {
                scope.nimbbComplete({
                    commentid: scope.commentid,
                    digest: true
                });
            }
        };
    };

    /**
     * linking function for an HTML5-based player
     * @param {object} scope
     */
    var html5PlayerLinkingFunction = function html5PlayerLinkingFunction(scope) {
        if (!scope.nimbbGuid) {
            return;
        }
        scope.playerType = 'html5';
        scope.init = 'http://api.nimbb.com/Live/Play.aspx?guid=' + scope.nimbbGuid + '&key=' + publicKey;
    };

    /**
     * @see {@link http://nimbb.com/Doc/Tutorials/Mobile.aspx}
     */
    var linkingFunction = config.forceHtml5Player || navigator.userAgent.match(/(iPad|iPhone|iPod)/g) ? html5PlayerLinkingFunction : flashPlayerLinkingFunction;

    /**
     * @type {object}
     */
    return {
        restrict: 'E',
        replace: true,
        scope: {
            commentid: '@',
            isSubject: '@',
            nimbbGuid: '@',
            nimbbControl: '=',
            nimbbComplete: '&'
        },
        link: linkingFunction,
        templateUrl: config.baseurl + '/partials/playNimbbVideo.twig'
    };
}]);

app.directive('file', function () {
    return {
        restrict: 'C',
        link: function link(scope, element) {
            element.bind('change', function (e) {
                if (e.target.files[0]) {
                    scope.uploadedfile = e.target.files[0].name;
                    scope.$digest();
                }
            });

            scope.$watch('uploadedfile', function () {
                if (!scope.uploadedfile) {
                    element[0].value = '';
                }
            });
        }
    };
});

},{}],5:[function(require,module,exports){
'use strict';

var app = angular.module('talkpointsApp.services', []);

app.service('talkpointsSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.baseurl + config.api + '/talkpoint/' + config.instanceid;

    this.getPageOfTalkpoints = function (page, perPage) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.getTalkpoint = function (talkpointid) {
        var deferred = $q.defer();
        $http.get(url + '/' + talkpointid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.deleteTalkpoint = function (talkpointid) {
        var deferred = $q.defer();
        // workaround Moodle's JavaScript minifier breaking due to the 'delete' keyword
        var prop = 'delete';
        var f = $http[prop];
        f(url + '/' + talkpointid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };
}]);

app.service('commentsSrv', ['$http', '$q', 'CONFIG', function ($http, $q, config) {
    var url = config.baseurl + config.api + '/talkpoint/' + config.instanceid + '/' + config.talkpointid + '/comment';

    this.getPageOfComments = function (page, perPage) {
        var deferred = $q.defer();
        $http.get(url + '?limitfrom=' + page * perPage + '&limitnum=' + perPage).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };

    this.postTextComment = function (textcomment, finalfeedback) {
        var deferred = $q.defer();
        var data = {
            textcomment: textcomment,
            finalfeedback: finalfeedback
        };
        $http.post(url, data).success(function (d) {
            deferred.resolve(d);
        }).error(function (d) {
            deferred.reject(d);
        });
        return deferred.promise;
    };

    this.postNimbbComment = function (guid, finalfeedback) {
        var deferred = $q.defer();
        var data = {
            nimbbguidcomment: guid,
            finalfeedback: finalfeedback
        };
        $http.post(url, data).success(function (d) {
            deferred.resolve(d);
        }).error(function (d) {
            deferred.reject(d);
        });
        return deferred.promise;
    };

    this.putTextComment = function (commentid, textcomment) {
        var deferred = $q.defer();
        var data = {
            textcomment: textcomment
        };
        $http.put(url + '/' + commentid, data).success(function (d) {
            deferred.resolve(d);
        }).error(function (d) {
            deferred.reject(d);
        });
        return deferred.promise;
    };

    this.deleteComment = function (commentid) {
        var deferred = $q.defer();
        // workaround Moodle's JavaScript minifier breaking due to the 'delete' keyword
        var prop = 'delete';
        var f = $http[prop];
        f(url + '/' + commentid).success(function (data) {
            deferred.resolve(data);
        }).error(function (data) {
            deferred.reject(data);
        });
        return deferred.promise;
    };
}]);

},{}]},{},[2]);
