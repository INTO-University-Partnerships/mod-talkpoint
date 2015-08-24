'use strict';

var app = angular.module('talkpointsApp.controllers', []);

app.controller('talkpointCtrl', [
    '$scope', '$timeout', '$window', 'talkpointsSrv', 'commentsSrv', 'CONFIG',
    function ($scope, $timeout, $window, talkpointsSrv, commentsSrv, config) {
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

        talkpointsSrv.getTalkpoint(config.talkpointid).
            then(function (data) {
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
            commentsSrv.getPageOfComments(currentPage, $scope.perPage).
                then(function (data) {
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
            commentsSrv.postTextComment(textcomment, finalfeedback).
                then(function () {
                    $scope.getPageOfComments($scope.currentPage);
                });
        };

        $scope.postNimbbComment = function (guid, finalfeedback) {
            $scope.talkpointClosed = finalfeedback;
            commentsSrv.postNimbbComment(guid, finalfeedback).
                then(function () {
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
            commentsSrv.putTextComment(comment.id, textcomment).
                then(function (data) {
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
            commentsSrv.deleteComment(commentid).
                then(function () {
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
    }
]);

app.controller('talkpointsCtrl', [
    '$scope', '$timeout', '$window', 'talkpointsSrv', 'CONFIG',
    function ($scope, $timeout, $window, talkpointsSrv, config) {
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
            talkpointsSrv.getPageOfTalkpoints(currentPage, $scope.perPage).
                then(function (data) {
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
            talkpointsSrv.deleteTalkpoint(talkpointid).
                then(function () {
                    $scope.getPageOfTalkpoints($scope.currentPage);
                });
        };
    }
]);

app.controller('talkpointsAddEditCtrl', [
    '$scope', '$window', 'CONFIG',
    function ($scope, $window, config) {
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

        $scope.changeMediaType = function (type) {
            // don't show confirm when there wasn't anything uploaded before
            if ($scope.mediaType && ($scope.nimbbguid || $scope.uploadedfile)) {
                if (!$window.confirm(config.messages.confirmdeletefile)) {
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
    }
]);
