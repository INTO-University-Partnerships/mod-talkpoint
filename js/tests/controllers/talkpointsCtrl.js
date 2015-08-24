'use strict';

describe('talkpointsCtrl', function () {
    var scope, configMock, windowMock, talkpointsSrvMock, $timeout, $q, deferred;

    beforeEach(angular.mock.module('talkpointsApp.controllers'));

    beforeEach(inject(function ($rootScope, $controller, _$timeout_, _$q_) {
        scope = $rootScope.$new();
        $q = _$q_;
        configMock = {
            baseurl: 'http://foobar.com/talkpoints',
            api: 'api/v1',
            instanceid: 8,
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
        talkpointsSrvMock = {
            deleteTalkpoint: function () {
                deferred = $q.defer();
                return deferred.promise;
            },
            getPageOfTalkpoints: function () {
                deferred = $q.defer();
                return deferred.promise;
            }
        };
        $timeout = _$timeout_;
        $controller('talkpointsCtrl', {
            $scope: scope,
            $timeout: $timeout,
            $window: windowMock,
            talkpointsSrv: talkpointsSrvMock,
            CONFIG: configMock
        });
    }));

    describe('method addTalkpoint', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.addTalkpoint)).toBeTruthy();
        });

        it('should navigate to the page that lets you add a new talkpoint', function () {
            scope.addTalkpoint();
            expect(windowMock.location.href).toEqual(configMock.baseurl + '/' + configMock.instanceid + '/add');
        });
    });

    describe('method editTalkpoint', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.editTalkpoint)).toBeTruthy();
        });

        it('should navigate to the page that lets you edit an existing talkpoint', function () {
            scope.editTalkpoint(13);
            expect(windowMock.location.href).toEqual(configMock.baseurl + '/' + configMock.instanceid + '/edit/13');
        });
    });

    describe('method deleteTalkpoint', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.deleteTalkpoint)).toBeTruthy();
        });

        it('should provide a confirmation dialog', function () {
            spyOn(windowMock, 'confirm');
            scope.deleteTalkpoint(13);
            expect(windowMock.confirm.calls.count()).toEqual(1);
        });

        it('should delegate to the talkpointsSrv service', function () {
            spyOn(windowMock, 'confirm').and.returnValue(true);
            spyOn(talkpointsSrvMock, 'deleteTalkpoint').and.callThrough();
            scope.deleteTalkpoint(13);
            expect(talkpointsSrvMock.deleteTalkpoint).toHaveBeenCalledWith(13);
            expect(talkpointsSrvMock.deleteTalkpoint.calls.count()).toEqual(1);
        });

        it('should get page of talkpoints when resolved', function () {
            scope.$digest();
            spyOn(windowMock, 'confirm').and.returnValue(true);
            spyOn(talkpointsSrvMock, 'deleteTalkpoint').and.callThrough();
            spyOn(scope, 'getPageOfTalkpoints');
            scope.deleteTalkpoint(13);
            deferred.resolve();
            expect(scope.getPageOfTalkpoints).not.toHaveBeenCalled();
            scope.$digest();
            expect(scope.getPageOfTalkpoints).toHaveBeenCalledWith(scope.currentPage);
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

    describe('method getPageOfTalkpoints', function () {
        it('should exist', function () {
            expect(angular.isFunction(scope.getPageOfTalkpoints)).toBeTruthy();
        });

        it('should delegate to the talkpointsSrv service', function () {
            var i;
            spyOn(talkpointsSrvMock, 'getPageOfTalkpoints').and.callThrough();
            for (i = 0; i < 5; ++i) {
                scope.getPageOfTalkpoints(i);
                expect(talkpointsSrvMock.getPageOfTalkpoints).toHaveBeenCalledWith(i, scope.perPage);
            }
            expect(talkpointsSrvMock.getPageOfTalkpoints.calls.count()).toEqual(5);
        });

        it('should set some scope variables when resolved', function () {
            scope.$digest();
            spyOn(scope, 'getPageOfTalkpoints');
            scope.getPageOfTalkpoints(7);
            deferred.resolve({
                talkpoints: [
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
                total: 3
            });
            scope.$digest();
            expect(scope.talkpoints).toEqual([
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
        });

        it('should call itself again after a 10s timeout', function () {
            scope.$digest();
            spyOn(scope, 'getPageOfTalkpoints');
            scope.getPageOfTalkpoints(9);
            deferred.resolve({
                talkpoints: [],
                total: 0
            });
            expect(scope.getPageOfTalkpoints.calls.count()).toEqual(1);
            scope.$digest();
            $timeout.flush(9999);
            expect(scope.getPageOfTalkpoints.calls.count()).toEqual(1);
            $timeout.flush(1);
            expect(scope.getPageOfTalkpoints.calls.count()).toEqual(2);
        });

        it("should cancel any pending promise that hasn't been resolved", function () {
            spyOn($timeout, 'cancel');
            spyOn(talkpointsSrvMock, 'getPageOfTalkpoints').and.returnValue({
                then: function () {}
            });
            scope.timeoutPromise = $timeout(function () {}, 1000);
            scope.getPageOfTalkpoints(0);
            expect($timeout.cancel).toHaveBeenCalledWith(scope.timeoutPromise);
        });
    });

    describe('currentPage watch', function () {
        it('should invoke getPageOfTalkpoints when the controller is initialized', function () {
            spyOn(scope, 'getPageOfTalkpoints');
            scope.$digest();
            expect(scope.getPageOfTalkpoints).toHaveBeenCalledWith(0);
        });

        it('should invoke getPageOfTalkpoints with newValue when changed', function () {
            spyOn(scope, 'getPageOfTalkpoints');
            scope.$digest();

            scope.currentPage = 1;
            scope.$digest();
            expect(scope.getPageOfTalkpoints).toHaveBeenCalledWith(1);

            scope.currentPage = 2;
            scope.$digest();
            expect(scope.getPageOfTalkpoints).toHaveBeenCalledWith(2);

            expect(scope.getPageOfTalkpoints.calls.count()).toEqual(3);
        });
    });
});
