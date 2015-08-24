'use strict';

describe('talkpointCtrl', function () {
    var scope, configMock, windowMock, talkpointsSrvMock, commentsSrvMock, $timeout, $q, deferred;

    beforeEach(angular.mock.module('talkpointsApp.controllers'));

    beforeEach(inject(function ($rootScope, $controller, _$timeout_, _$q_) {
        scope = $rootScope.$new();
        $q = _$q_;
        configMock = {
            baseurl: 'http://foobar.com/talkpoints',
            api: 'api/v1',
            instanceid: 8,
            talkpointid: 17,
            canManage: true,
            messages: {
                confirm: 'Are you sure?',
                confirmdeletefile: 'The file will not be saved. Are you sure you want to change?',
                submit: 'You must upload a file or record a webcam video!'
            }
        };
        windowMock = {
            location: {
                href: ''
            },
            confirm: function () {}
        };
        deferred = [];
        talkpointsSrvMock = {
            getTalkpoint: function () {
                deferred.getTalkpoint = $q.defer();
                return deferred.getTalkpoint.promise;
            }
        };
        commentsSrvMock = {
            getPageOfComments: function () {
                deferred.getPageOfComments = $q.defer();
                return deferred.getPageOfComments.promise;
            },
            postTextComment: function () {
                deferred.postTextComment = $q.defer();
                return deferred.postTextComment.promise;
            },
            postNimbbComment: function () {
                deferred.postNimbbComment = $q.defer();
                return deferred.postNimbbComment.promise;
            },
            putTextComment: function () {
                deferred.putTextComment = $q.defer();
                return deferred.putTextComment.promise;
            },
            deleteComment: function () {
                deferred.deleteComment = $q.defer();
                return deferred.deleteComment.promise;
            }
        };
        spyOn(talkpointsSrvMock, 'getTalkpoint').and.callThrough();
        spyOn(commentsSrvMock, 'getPageOfComments').and.callThrough();
        $timeout = _$timeout_;
        $controller('talkpointCtrl', {
            $scope: scope,
            $timeout: $timeout,
            $window: windowMock,
            talkpointsSrv: talkpointsSrvMock,
            commentsSrv: commentsSrvMock,
            CONFIG: configMock
        });
    }));

    describe('initialization', function () {
        it('should invoke the getTalkpoint method of its injected talkpointsSrvMock service', function () {
            scope.$digest();
            deferred.getTalkpoint.resolve({
                id: 9
            });
            scope.$digest();
            expect(talkpointsSrvMock.getTalkpoint).toHaveBeenCalledWith(17);
            expect(talkpointsSrvMock.getTalkpoint.calls.count()).toEqual(1);
            expect(scope.talkpoint.id).toEqual(9);
        });

        it('should invoke the getPageOfComments method', function () {
            spyOn(scope, 'getPageOfComments');
            scope.$digest();
            expect(scope.getPageOfComments).toHaveBeenCalledWith(0);
        });

        it("should cancel any pending promise that hasn't been resolved", function () {
            spyOn($timeout, 'cancel');
            scope.timeoutPromise = $timeout(function () {}, 1000);
            scope.getPageOfComments(0);
            expect($timeout.cancel).toHaveBeenCalledWith(scope.timeoutPromise);
        });
    });

    describe('method getPageOfComments', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.getPageOfComments)).toBeTruthy();
        });

        it('should delegate to the commentsSrv service', function () {
            var i;
            commentsSrvMock.getPageOfComments.calls.reset();
            for (i = 0; i < 5; ++i) {
                scope.getPageOfComments(i);
                expect(commentsSrvMock.getPageOfComments).toHaveBeenCalledWith(i, scope.perPage);
            }
            expect(commentsSrvMock.getPageOfComments.calls.count()).toEqual(5);
        });

        it('should set some scope variables when resolved', function () {
            commentsSrvMock.getPageOfComments.calls.reset();
            scope.$digest();
            spyOn(scope, 'getPageOfComments');
            scope.getPageOfComments(7);
            deferred.getPageOfComments.resolve({
                comments: [
                    {
                        id: 3
                    },
                    {
                        id: 5
                    },
                    {
                        id: 8
                    }
                ],
                total: 3,
                talkpointClosed: false
            });
            scope.$digest();
            expect(scope.comments).toEqual([
                {
                    id: 3
                },
                {
                    id: 5
                },
                {
                    id: 8
                }
            ]);
            expect(scope.total).toEqual(3);
            expect(scope.talkpointClosed).toBeFalsy();
        });

        it('should preserve any existing speech bubbles', function () {
            scope.commentsExtra = {
                3: {
                    // empty
                },
                5: {
                    speechBubble: 'Lalala'
                }
            };
            commentsSrvMock.getPageOfComments.calls.reset();
            scope.$digest();
            spyOn(scope, 'getPageOfComments');
            scope.getPageOfComments(7);
            deferred.getPageOfComments.resolve({
                comments: [
                    {
                        id: 3
                    },
                    {
                        id: 5,
                        textcomment: 'Hehehe'
                    }
                ],
                total: 2,
                talkpointClosed: false
            });
            expect(scope.commentsExtra[5].speechBubble).toEqual('Lalala');
            scope.$digest();
            expect(scope.commentsExtra[5].speechBubble).toEqual('Hehehe');
        });

        it('should call itself again after a 10s timeout', function () {
            scope.$digest();
            spyOn(scope, 'getPageOfComments');
            scope.getPageOfComments(9);
            deferred.getPageOfComments.resolve({
                comments: [],
                total: 0,
                talkpointClosed: false
            });
            expect(scope.getPageOfComments.calls.count()).toEqual(1);
            scope.$digest();
            $timeout.flush(9999);
            expect(scope.getPageOfComments.calls.count()).toEqual(1);
            $timeout.flush(1);
            expect(scope.getPageOfComments.calls.count()).toEqual(2);
        });

        it("should cancel any pending promise that hasn't been resolved", function () {
            spyOn($timeout, 'cancel');
            scope.timeoutPromise = $timeout(function () {}, 1000);
            scope.getPageOfComments(0);
            expect($timeout.cancel).toHaveBeenCalledWith(scope.timeoutPromise);
        });
    });

    describe('method postTextComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.postTextComment)).toBeTruthy();
        });

        it('should delegate to the commentsSrv service', function () {
            var textcomment = 'What a great thing you have uploaded!';
            spyOn(commentsSrvMock, 'postTextComment').and.returnValue({
                then: function () {}
            });
            scope.postTextComment(textcomment, false);
            expect(commentsSrvMock.postTextComment).toHaveBeenCalledWith(textcomment, false);
            expect(commentsSrvMock.postTextComment.calls.count()).toEqual(1);
        });

        it('should get page of comments when resolved', function () {
            var textcomment = 'What a great thing you have uploaded!';
            scope.$digest();
            spyOn(commentsSrvMock, 'postTextComment').and.callThrough();
            spyOn(scope, 'getPageOfComments');
            scope.postTextComment(textcomment, false);
            deferred.postTextComment.resolve();
            expect(scope.getPageOfComments).not.toHaveBeenCalled();
            scope.$digest();
            expect(scope.getPageOfComments).toHaveBeenCalledWith(scope.currentPage);
        });
    });

    describe('method postNimbbComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.postNimbbComment)).toBeTruthy();
        });

        it('should delegate to the commentsSrv service', function () {
            var nimbbguidcomment = 'abc123';
            spyOn(commentsSrvMock, 'postNimbbComment').and.returnValue({
                then: function () {}
            });
            scope.postNimbbComment(nimbbguidcomment, false);
            expect(commentsSrvMock.postNimbbComment).toHaveBeenCalledWith(nimbbguidcomment, false);
            expect(commentsSrvMock.postNimbbComment.calls.count()).toEqual(1);
        });

        it('should get page of comments when resolved', function () {
            var nimbbguidcomment = 'abc123';
            scope.$digest();
            spyOn(commentsSrvMock, 'postNimbbComment').and.callThrough();
            spyOn(scope, 'getPageOfComments');
            scope.postNimbbComment(nimbbguidcomment, false);
            deferred.postNimbbComment.resolve();
            expect(scope.getPageOfComments).not.toHaveBeenCalled();
            scope.$digest();
            expect(scope.getPageOfComments).toHaveBeenCalledWith(scope.currentPage);
        });
    });

    describe('method putTextComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.putTextComment)).toBeTruthy();
        });

        it('should delegate to the commentsSrv service', function () {
            var textcomment = 'What a great thing you have there!';
            var comment = {
                id: 17
            };
            scope.commentsExtra[comment.id] = {
                textcomment: textcomment
            };
            spyOn(commentsSrvMock, 'putTextComment').and.returnValue({
                then: function () {}
            });
            scope.putTextComment(comment);
            expect(commentsSrvMock.putTextComment).toHaveBeenCalledWith(comment.id, textcomment);
            expect(commentsSrvMock.putTextComment.calls.count()).toEqual(1);
        });

        it('should stop editing', function () {
            var textcomment = 'What a great thing you have there!';
            var comment = {
                id: 17
            };
            scope.commentsExtra[comment.id] = {
                textcomment: textcomment,
                editing: true
            };
            spyOn(commentsSrvMock, 'putTextComment').and.returnValue({
                then: function () {}
            });
            scope.putTextComment(comment);
            expect(scope.commentsExtra[comment.id].textcomment).toBe('');
            expect(scope.commentsExtra[comment.id].editing).toBeFalsy();
        });

        it('should get page of comments when resolved', function () {
            var textcomment = 'What a great thing you have uploaded!';
            var comment = {
                id: 17
            };
            scope.commentsExtra[comment.id] = {
                textcomment: textcomment,
                speechBubble: 'Lalala',
                editing: true
            };
            scope.$digest();
            spyOn(commentsSrvMock, 'putTextComment').and.callThrough();
            spyOn(scope, 'getPageOfComments');
            scope.putTextComment(comment);
            deferred.putTextComment.resolve({
                textcomment: 'Hehehe'
            });
            expect(scope.getPageOfComments).not.toHaveBeenCalled();
            scope.$digest();
            expect(scope.getPageOfComments).toHaveBeenCalledWith(scope.currentPage);
        });
    });

    describe('method deleteComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.deleteComment)).toBeTruthy();
        });

        it('should provide a confirmation dialog', function () {
            spyOn(windowMock, 'confirm');
            scope.deleteComment(13);
            expect(windowMock.confirm.calls.count()).toEqual(1);
        });

        it('should delegate to the commentsSrv service', function () {
            spyOn(windowMock, 'confirm').and.returnValue(true);
            spyOn(commentsSrvMock, 'deleteComment').and.returnValue({
                then: function () {}
            });
            scope.deleteComment(17);
            expect(commentsSrvMock.deleteComment).toHaveBeenCalledWith(17);
            expect(commentsSrvMock.deleteComment.calls.count()).toEqual(1);
        });

        it('should get page of comments when resolved', function () {
            spyOn(windowMock, 'confirm').and.returnValue(true);
            var comment = {
                id: 17
            };
            scope.commentsExtra[comment.id] = {};
            scope.$digest();
            spyOn(commentsSrvMock, 'deleteComment').and.callThrough();
            spyOn(scope, 'getPageOfComments');
            scope.deleteComment(comment.id);
            deferred.deleteComment.resolve();
            expect(scope.getPageOfComments).not.toHaveBeenCalled();
            scope.$digest();
            expect(scope.getPageOfComments).toHaveBeenCalledWith(scope.currentPage);
            expect(scope.commentsExtra[comment.id]).not.toBeDefined();
        });
    });

    describe('method startEdit', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.startEdit)).toBeTruthy();
        });

        it('should set editing mode for the given comment', function () {
            var comment = {
                id: 13,
                textcomment: 'How long is a piece of string'
            };
            scope.startEdit(comment);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].textcomment).toEqual(comment.textcomment);
            expect(scope.commentsExtra[comment.id].editing).toBeTruthy();

            // execute the function again to force execution of another branch
            scope.startEdit(comment);
        });
    });

    describe('method stopEdit', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.stopEdit)).toBeTruthy();
        });

        it('should cancel editing mode for the given comment', function () {
            var comment = {
                id: 13,
                textcomment: 'How long is a piece of string'
            };
            scope.stopEdit(comment);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].textcomment).toBe('');
            expect(scope.commentsExtra[comment.id].editing).toBeFalsy();

            // execute the function again to force execution of another branch
            scope.stopEdit(comment);
        });
    });

    describe('method backToTalkpoints', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.backToTalkpoints)).toBeTruthy();
        });

        it('should navigate to the talkpoints page (the activity page)', function () {
            scope.backToTalkpoints();
            expect(windowMock.location.href).toEqual(configMock.baseurl + '/' + configMock.instanceid);
        });
    });

    describe('method openComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.openComment)).toBeTruthy();
        });

        it('should set the speech bubble text to that of the given comment', function () {
            spyOn(scope, 'closeComment').and.returnValue(false);
            var comment = {
                id: 17,
                textcomment: 'The quick brown fox'
            };
            scope.openComment(comment);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].nimbbGuid).not.toBeDefined();
            expect(scope.commentsExtra[comment.id].speechBubble).toEqual(comment.textcomment);
        });

        it('should close the currently open comment', function () {
            var comment = {
                id: 17,
                textcomment: 'The quick brown fox'
            };
            scope.commentsExtra[comment.id] = {
                speechBubble: comment.textcomment
            };
            spyOn(scope, 'getPageOfComments');
            scope.openComment(comment);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].speechBubble).toBe('');
            expect(scope.getPageOfComments).toHaveBeenCalled();
        });

        it('should close all (other) open comments', function () {
            var comment1 = {
                id: 17,
                textcomment: 'The quick brown fox'
            };
            var comment2 = {
                id: 13,
                textcomment: 'Sorting out the kitchen pans'
            };
            scope.commentsExtra[comment1.id] = {
                speechBubble: comment1.textcomment
            };
            scope.openComment(comment2);
            expect(scope.commentsExtra[comment1.id]).toBeDefined();
            expect(scope.commentsExtra[comment1.id].speechBubble).toBe('');
        });

        it('should start Nimbb video playing if a Nimbb video comment is opened', function () {
            spyOn(scope, 'closeComment').and.returnValue(false);
            var comment = {
                id: 17,
                nimbbguidcomment: 'abc123'
            };
            scope.openComment(comment);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].speechBubble).not.toBeDefined();
            expect(scope.commentsExtra[comment.id].nimbbGuid).toEqual('abc123');
        });

        it('should still execute if neither textcomment nor nimbbguidcomment are given', function () {
            spyOn(scope, 'closeComment').and.returnValue(false);
            var comment = {
                id: 17
            };
            scope.openComment(comment);
        });
    });

    describe('method closeComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.closeComment)).toBeTruthy();
        });

        it('should set the Nimbb GUID to empty', function () {
            var comment = {
                id: 13,
                nimbbguidcomment: 'abc123'
            };
            scope.commentsExtra[comment.id] = {
                nimbbGuid: comment.nimbbguidcomment
            };
            scope.closeComment(comment.id, false);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].nimbbGuid).toBe('');
        });

        it('should set the speech bubble to empty', function () {
            var comment = {
                id: 19,
                textcomment: 'The quick brown fox'
            };
            scope.commentsExtra[comment.id] = {
                speechBubble: comment.textcomment
            };
            scope.closeComment(comment.id, false);
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].speechBubble).toBe('');
        });

        it('should set the Nimbb guid comment to empty', function () {
            var comment = {
                id: 19,
                nimbbguidcomment: 'abc123'
            };
            scope.isNimbbVideoPlaying = true;
            scope.commentsExtra[comment.id] = {
                nimbbGuid: 'abc123'
            };
            scope.closeComment(comment.id, false);
            expect(scope.isNimbbVideoPlaying).toBeFalsy();
            expect(scope.commentsExtra[comment.id]).toBeDefined();
            expect(scope.commentsExtra[comment.id].nimbbGuid).toBe('');
        });

        it('should force a $digest and get a page of comments if told to', function () {
            var comment = {
                id: 19,
                nimbbguidcomment: 'abc123'
            };
            scope.isNimbbVideoPlaying = true;
            scope.commentsExtra[comment.id] = {
                nimbbGuid: 'abc123'
            };
            spyOn(scope, 'getPageOfComments');
            expect(scope.getPageOfComments).not.toHaveBeenCalled();
            scope.closeComment(comment.id, true);
            expect(scope.getPageOfComments).toHaveBeenCalledWith(scope.currentPage);
        });

        it('should do nothing if told to close a comment for which no matching extra data has been defined', function () {
            var comment = {
                id: 13,
                nimbbguidcomment: 'abc123'
            };
            var result = scope.closeComment(comment.id);
            expect(result).toBeFalsy();
        });
    });

    describe('Nimbb Control', function () {
        var idPlayer, nimbbEvents;

        beforeEach(function () {
            idPlayer = 'foobar';
            nimbbEvents = [
                'initCompleted',
                'recordingStopped',
                'videoSaved',
                'stateChanged',
                'modeChanged',
                'playbackStopped'
            ];
            scope.nimbbControl[idPlayer] = {};
            angular.forEach(nimbbEvents, function (event) {
                scope.nimbbControl[idPlayer]['Nimbb_' + event] = function () {};
            });
        });

        it('should support a subset of the Nimbb events (that must be defined on window)', function () {
            angular.forEach(nimbbEvents, function (event) {
                spyOn(scope.nimbbControl[idPlayer], 'Nimbb_' + event);
                windowMock['Nimbb_' + event](idPlayer);
                expect(scope.nimbbControl[idPlayer]['Nimbb_' + event]).toHaveBeenCalled();
                expect(scope.nimbbControl[idPlayer]['Nimbb_' + event].calls.count()).toEqual(1);
            });
        });
    });

    describe('method prevPage', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.prevPage)).toBeTruthy();
        });

        it('should move to the previous page', function () {
            scope.currentPage = 1;
            scope.prevPage();
            expect(scope.currentPage).toEqual(0);
        });

        it('should do nothing if on the first page', function () {
            scope.currentPage = 0;
            scope.prevPage();
            expect(scope.currentPage).toEqual(0);
        });
    });

    describe('method prevPageDisabled', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.prevPageDisabled)).toBeTruthy();
        });

        it('should return "disabled" if on the first page', function () {
            scope.currentPage = 0;
            expect(scope.prevPageDisabled()).toEqual('disabled');
        });

        it('should return "" (i.e. an empty string) if not on the first page', function () {
            var i;
            for (i = 1; i < 10; ++i) {
                scope.currentPage = i;
                expect(scope.prevPageDisabled()).toEqual('');
            }
        });

        it('should return "disabled" if a Nimbb video is playing', function () {
            scope.isNimbbVideoPlaying = true;
            scope.currentPage = 1;
            expect(scope.prevPageDisabled()).toEqual('disabled');
        });
    });

    describe('method nextPage', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.nextPage)).toBeTruthy();
        });

        it('should move to the next page', function () {
            scope.currentPage = 8;
            spyOn(scope, 'pageCount').and.returnValue(10);
            scope.nextPage();
            expect(scope.currentPage).toEqual(9);
        });

        it('should do nothing if on the last page', function () {
            scope.currentPage = 9;
            spyOn(scope, 'pageCount').and.returnValue(10);
            scope.nextPage();
            expect(scope.currentPage).toEqual(9);
        });
    });

    describe('method nextPageDisabled', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.nextPageDisabled)).toBeTruthy();
        });

        it('should return "disabled" if on the last page', function () {
            scope.currentPage = 9;
            spyOn(scope, 'pageCount').and.returnValue(10);
            expect(scope.nextPageDisabled()).toEqual('disabled');
        });

        it('should return "" (i.e. an empty string) if not on the last page', function () {
            var i;
            spyOn(scope, 'pageCount').and.returnValue(10);
            for (i = 0; i < 9; ++i) {
                scope.currentPage = i;
                expect(scope.nextPageDisabled()).toEqual('');
            }
        });

        it('should return "disabled" if a Nimbb video is playing', function () {
            scope.isNimbbVideoPlaying = true;
            spyOn(scope, 'pageCount').and.returnValue(2);
            scope.currentPage = 0;
            expect(scope.nextPageDisabled()).toEqual('disabled');
        });
    });

    describe('method pageCount', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.pageCount)).toBeTruthy();
        });

        it('should calculate the number of pages correctly', function () {
            expect(scope.perPage).toEqual(5);
            scope.total = 24;
            expect(scope.pageCount()).toEqual(5);
            scope.total = 25;
            expect(scope.pageCount()).toEqual(5);
            scope.total = 26;
            expect(scope.pageCount()).toEqual(6);
            scope.total = 5;
            expect(scope.pageCount()).toEqual(1);
            scope.total = 4;
            expect(scope.pageCount()).toEqual(1);

            // we want to tell the user there's one page, even if there's no items on it
            scope.total = 0;
            expect(scope.pageCount()).toEqual(1);
        });
    });
});
