'use strict';

describe('talkpointsAddEditCtrl', function () {
    var scope, configMock, windowMock;

    beforeEach(angular.mock.module('talkpointsApp.controllers'));

    beforeEach(inject(function ($rootScope, $controller) {
        scope = $rootScope.$new();
        configMock = {
            baseurl: 'http://foobar.com/talkpoints',
            mediaType: '',
            messages: {
                'confirm': 'Are you sure?',
                'confirmdeletefile': 'The file will not be saved. Are you sure you want to change?',
                'submit': 'You must upload a file or record a webcam video!',
                'webcam:showcurrent': 'Show current webcam recording',
                'webcam:recordnew': 'Record new video',
                'audio:showcurrent': 'Listen to current audio recording',
                'audio:recordnew': 'Record new audio'
            }
        };
        windowMock = {
            location: {
                href: ''
            },
            confirm: function () {}
        };
        $controller('talkpointsAddEditCtrl', {
            $scope: scope,
            $window: windowMock,
            CONFIG: configMock
        });
    }));

    describe('method saveMedia', function () {
        it('should exists', function () {
            expect(angular.isFunction(scope.saveMedia)).toBeTruthy();
        });

        it('should change scope.nimbbguid', function () {
            scope.nimbbguid = 'ABC123';
            scope.saveMedia('DEF456');
            expect(scope.nimbbguid).toBe('DEF456');
        });

        it('should broadcast an event', function () {
            scope.$digest();
            spyOn(scope, '$broadcast').and.callThrough();
            scope.$broadcast('nimbbchanged', 'DEF456');
            expect(scope.$broadcast).toHaveBeenCalled();
        });
    });

    describe('method toggleCurrentRecording', function () {
        it('should exists', function () {
            expect(angular.isFunction(scope.toggleCurrentRecording)).toBeTruthy();
        });

        it('should toggle variable showCurrentRecording', function () {
            scope.showCurrentRecording = false;
            scope.toggleCurrentRecording();
            expect(scope.showCurrentRecording).toBeTruthy();
        });

        it('should toggle the label to webcam:recordnew when mediaType is webcam', function () {
            scope.mediaType = 'webcam';
            scope.toggleButtonLabel = configMock.messages['webcam:showcurrent'];
            scope.toggleCurrentRecording();
            expect(scope.toggleButtonLabel).toBe(configMock.messages['webcam:recordnew']);
        });

        it('should toggle the label to audio:recordnew when mediaType is audio', function () {
            scope.mediaType = 'audio';
            scope.toggleButtonLabel = configMock.messages['audio:showcurrent'];
            scope.toggleCurrentRecording();
            expect(scope.toggleButtonLabel).toBe(configMock.messages['audio:recordnew']);
        });
    });

    describe('method changeMediaType', function () {
        it('should exists', function () {
            expect(angular.isFunction(scope.changeMediaType)).toBeTruthy();
        });

        it('should trigger window confirm if media gets changed', function () {
            scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            spyOn(windowMock, 'confirm');
            scope.changeMediaType('file');
            expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages.confirmdeletefile);
        });

        it('should empty nimbbguid and uploadedfile', function () {
            scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.uploadedfile = '';
            spyOn(windowMock, 'confirm').and.returnValue(true);
            scope.changeMediaType('file');
            expect(windowMock.confirm).toHaveBeenCalled();
            expect(scope.nimbbguid).toBe('');
            expect(scope.uploadedfile).toBe('');
        });

        it('should change mediatype', function () {
            scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            spyOn(windowMock, 'confirm').and.returnValue(true);
            scope.changeMediaType('file');
            expect(windowMock.confirm).toHaveBeenCalled();
            expect(scope.mediaType).toBe('file');
        });

        it('should set showCurrentRecording to false', function () {
            scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.showCurrentRecording = true;
            spyOn(windowMock, 'confirm').and.returnValue(true);
            scope.changeMediaType('file');
            expect(windowMock.confirm).toHaveBeenCalled();
            expect(scope.showCurrentRecording).toBeFalsy();
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
                'stateChanged'
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
});
