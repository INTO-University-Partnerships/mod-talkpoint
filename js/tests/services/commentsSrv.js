'use strict';

describe('commentsSrv', function () {
    var srv, $httpBackend, configMock;

    beforeEach(module('talkpointsApp.services', function ($provide) {
        configMock = {
            baseurl: 'http://foobar.com/talkpoints',
            api: '/api/v1',
            instanceid: 8,
            talkpointid: 17
        };
        $provide.value('CONFIG', configMock);
    }));

    beforeEach(inject(function (commentsSrv, _$httpBackend_) {
        srv = commentsSrv;
        $httpBackend = _$httpBackend_;
    }));

    afterEach(function () {
        $httpBackend.verifyNoOutstandingExpectation();
        $httpBackend.verifyNoOutstandingRequest();
    });

    describe('method getPageOfComments', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.getPageOfComments)).toBeTruthy();
        });

        it('should handle errors', function () {
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment?limitfrom=0&limitnum=5').
                respond(500, 'something bad happened');
            var promise = srv.getPageOfComments(0, 5);
            var spy = jasmine.createSpy();
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should should fetch data from the server', function () {
            var data = {
                comments: [
                    {
                        id: 6,
                        talkpointid: 42,
                        userid: 3,
                        userfullname: "Tyrion Lannister",
                        is_owner: true,
                        textcomment: "How fantastic!",
                        nimbbguidcomment: null,
                        finalfeedback: false,
                        timecreated: 1384538546,
                        timemodified: 1384788955
                    },
                    {
                        id: 9,
                        talkpointid: 42,
                        userid: 3,
                        userfullname: "Tyrion Lannister",
                        is_owner: true,
                        textcomment: "Actually, it's not that great afterall.",
                        nimbbguidcomment: null,
                        finalfeedback: false,
                        timecreated: 1384538546,
                        timemodified: 1384788955
                    }
                ],
                total: 2
            };
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment?limitfrom=0&limitnum=5').
                respond(data);
            var promise = srv.getPageOfComments(0, 5);
            var spy = jasmine.createSpy();
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith(data);
        });
    });

    describe('method postTextComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.postTextComment)).toBeTruthy();
        });

        it('should handle errors', function () {
            var textcomment = 'What a great thing you have there!';
            var data = {
                textcomment: textcomment,
                finalfeedback: false
            };
            $httpBackend.expectPOST('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment', data).
                respond(500, 'something bad happened');
            var promise = srv.postTextComment(textcomment, false);
            var spy = jasmine.createSpy();
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should POST data to the server', function () {
            var textcomment = 'What a great thing you have there!';
            var data = {
                textcomment: textcomment,
                finalfeedback: false
            };
            $httpBackend.expectPOST('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment', data).
                respond(204);
            var spy = jasmine.createSpy();
            var promise = srv.postTextComment(textcomment, false);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalled();
        });
    });

    describe('method postNimbbComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.postNimbbComment)).toBeTruthy();
        });

        it('should handle errors', function () {
            var nimbbguidcomment = 'abc123';
            var data = {
                nimbbguidcomment: nimbbguidcomment,
                finalfeedback: false
            };
            $httpBackend.expectPOST('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment', data).
                respond(500, 'something bad happened');
            var spy = jasmine.createSpy();
            var promise = srv.postNimbbComment(nimbbguidcomment, false);
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should POST data to the server', function () {
            var nimbbguidcomment = 'abc123';
            var data = {
                nimbbguidcomment: nimbbguidcomment,
                finalfeedback: false
            };
            $httpBackend.expectPOST('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment', data).
                respond(204);
            var spy = jasmine.createSpy();
            var promise = srv.postNimbbComment(nimbbguidcomment, false);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalled();
        });
    });

    describe('method putTextComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.putTextComment)).toBeTruthy();
        });

        it('should handle errors', function () {
            var textcomment = 'What a great thing you have there!';
            var data = {
                textcomment: textcomment
            };
            $httpBackend.expectPUT('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment/21', data).
                respond(500, 'something bad happened');
            var spy = jasmine.createSpy();
            var promise = srv.putTextComment(21, textcomment);
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should PUT data to the server', function () {
            var textcomment = 'What a great thing you have there!';
            var data = {
                textcomment: textcomment
            };
            $httpBackend.expectPUT('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment/21', data).
                respond(200);
            var spy = jasmine.createSpy();
            var promise = srv.putTextComment(21, textcomment);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalled();
        });
    });

    describe('method deleteComment', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.deleteComment)).toBeTruthy();
        });

        it('should handle errors', function () {
            $httpBackend.expectDELETE('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment/21').
                respond(500, 'something bad happened');
            var promise = srv.deleteComment(21);
            var spy = jasmine.createSpy();
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should DELETE data from the server', function () {
            $httpBackend.expectDELETE('http://foobar.com/talkpoints/api/v1/talkpoint/8/17/comment/21').
                respond(200);
            var promise = srv.deleteComment(21);
            var spy = jasmine.createSpy();
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalled();
        });
    });
});
