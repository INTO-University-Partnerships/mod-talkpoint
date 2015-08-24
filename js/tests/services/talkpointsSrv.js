'use strict';

describe('talkpointsSrv', function () {
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

    beforeEach(inject(function (talkpointsSrv, _$httpBackend_) {
        srv = talkpointsSrv;
        $httpBackend = _$httpBackend_;
    }));

    afterEach(function () {
        $httpBackend.verifyNoOutstandingExpectation();
        $httpBackend.verifyNoOutstandingRequest();
    });

    describe('method getPageOfTalkpoints', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.getPageOfTalkpoints)).toBeTruthy();
        });

        it('should handle errors', function () {
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8?limitfrom=0&limitnum=5').
                respond(500, 'something bad happened');
            var spy = jasmine.createSpy();
            var promise = srv.getPageOfTalkpoints(0, 5);
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should fetch data from the server', function () {
            var data = {
                talkpoints: [
                    {
                        id: 23,
                        instanceid: 1,
                        userid: 3,
                        userfullname: "Tyrion Lannister",
                        is_owner: false,
                        title: "T4",
                        uploadedfile: "2013-11-12 13.04.28.jpg",
                        closed: false,
                        timecreated: 1384538546,
                        timemodified: 1384788955
                    },
                    {
                        id: 22,
                        instanceid: 1,
                        userid: 3,
                        userfullname: "Tyrion Lannister",
                        is_owner: false,
                        title: "T3",
                        uploadedfile: "django1920x1080.png",
                        closed: false,
                        timecreated: 1384531933,
                        timemodified: 1384791073
                    }
                ],
                total: 2
            };
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8?limitfrom=0&limitnum=5').
                respond(data);
            var spy = jasmine.createSpy();
            var promise = srv.getPageOfTalkpoints(0, 5);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith(data);
        });
    });

    describe('method getTalkpoint', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.getTalkpoint)).toBeTruthy();
        });

        it('should handle errors', function () {
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8/17').
                respond(500, 'something bad happened');
            var spy = jasmine.createSpy();
            var promise = srv.getTalkpoint(17);
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should fetch data from the server', function () {
            var data = {
                id: 23,
                instanceid: 1,
                userid: 3,
                userfullname: "Tyrion Lannister",
                is_owner: false,
                title: "T4",
                uploadedfile: "2013-11-12 13.04.28.jpg",
                closed: false,
                timecreated: 1384538546,
                timemodified: 1384788955
            };
            $httpBackend.expectGET('http://foobar.com/talkpoints/api/v1/talkpoint/8/17').
                respond(data);
            var spy = jasmine.createSpy();
            var promise = srv.getTalkpoint(17);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith(data);
        });
    });

    describe('method deleteTalkpoint', function () {
        it('should exist', function () {
            expect(angular.isFunction(srv.deleteTalkpoint)).toBeTruthy();
        });

        it('should handle errors', function () {
            $httpBackend.expectDELETE('http://foobar.com/talkpoints/api/v1/talkpoint/8/17').
                respond(500, 'something bad happened');
            var spy = jasmine.createSpy();
            var promise = srv.deleteTalkpoint(17);
            promise.then(function () {
                // empty
            }, spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalledWith('something bad happened');
        });

        it('should delete data from the server', function () {
            $httpBackend.expectDELETE('http://foobar.com/talkpoints/api/v1/talkpoint/8/17').
                respond(204);
            var spy = jasmine.createSpy();
            var promise = srv.deleteTalkpoint(17);
            promise.then(spy);
            expect(spy).not.toHaveBeenCalled();
            $httpBackend.flush();
            expect(spy).toHaveBeenCalled();
        });
    });
});
