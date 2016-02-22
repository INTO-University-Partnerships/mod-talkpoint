'use strict';

var app = angular.module('talkpointsApp.directives', []);

app.directive('talkpointListItem', ['CONFIG',
    function (config) {
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
    }
]);

app.directive('jplayer', ['CONFIG',
    function (config) {
        return {
            restrict: 'E',
            scope: {
                title: '@'
            },
            link: function (scope, element, attrs) {
                var jplayer = angular.element('#jquery_jplayer_1');
                jplayer.jPlayer({
                    ready: function () {
                        var obj = {};
                        console.info('INTO: Video formats', attrs.formats);
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
                    error: function (event) {
                        if (event.jPlayer.error.type === 'e_no_solution') {
                            var errorMsg = 'This video format is not supported by your browser.';
                            angular.element('.jp-no-solution').html(errorMsg);
                        }
                    }
                });
            },
            templateUrl: config.baseurl + '/partials/jplayer.twig'
        };
    }
]);

app.directive('addTextComment', ['CONFIG',
    function (config) {
        return {
            restrict: 'E',
            scope: {
                addingTextComment: '=',
                saveChanges: '&'
            },
            link: function (scope, element, attrs) {
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
    }
]);

app.directive('addMediaComment', ['NIMBB_SWF_URL', 'NIMBB_PUBLIC_KEY', 'CONFIG',
    function (swfUrl, publicKey, config) {
        return {
            restrict: 'E',
            scope: {
                addingMediaComment: '=',
                saveChanges: '&',
                cancelChanges: '&',
                nimbbControl: '=',
                mediaType: '='
            },
            link: function (scope, element, attrs) {
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
                scope.templateInit =
                    swfUrl +
                    '?mode=record' +
                    '&key=' + publicKey +
                    '&nologo=1&lang=en&simplepage=1&showmenu=0&showcounter=0';

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
    }
]);

app.directive('playNimbbVideo',
    ['NIMBB_SWF_URL', 'NIMBB_PUBLIC_KEY', 'CONFIG', '$compile',
    function (swfUrl, publicKey, config, $compile) {

        /**
         * linking function for the Flash-based Nimbb player
         * @param {object} scope
         * @param {object} element
         */
        var flashPlayerLinkingFunction = function (scope, element) {
            if (!scope.nimbbGuid) {
                return;
            }
            scope.playerType = 'flash';

            scope.unique = 'nimbb_' + scope.nimbbGuid;
            scope.nimbbControl[scope.unique] = {};
            scope.init =
                swfUrl +
                '?guid=' + scope.nimbbGuid +
                '&key=' + publicKey +
                '&autoplay=true&nologo=1&lang=en&simplepage=1&showmenu=1&showcounter=0&bgcolor=000000';

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
        var html5PlayerLinkingFunction = function (scope) {
            if (!scope.nimbbGuid) {
                return;
            }
            scope.playerType = 'html5';
            scope.init = 'http://api.nimbb.com/Live/Play.aspx?guid=' + scope.nimbbGuid + '&key=' + publicKey;
        };

        /**
         * @see {@link http://nimbb.com/Doc/Tutorials/Mobile.aspx}
         */
        var linkingFunction = config.forceHtml5Player || navigator.userAgent.match(/(iPad|iPhone|iPod)/g)
            ? html5PlayerLinkingFunction : flashPlayerLinkingFunction;

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
    }]
);

app.directive('file', function () {
    return {
        restrict: 'C',
        link: function (scope, element) {
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
