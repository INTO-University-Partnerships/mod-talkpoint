'use strict';

describe('talkpointsAddEditCtrl', function () {
    var scope, configMock, windowMock;

    beforeEach(angular.mock.module('talkpointsApp.controllers'));

    beforeEach(inject(function ($rootScope, $controller) {
        scope = $rootScope.$new();
        scope.file = {};
        configMock = {
            baseurl: 'http://foobar.com/talkpoints',
            mediaType: '',
            title: 'My new talkpoint',
            messages: {
                'confirm': 'Are you sure?',
                'file:confirmlose': 'The file you uploaded will be lost. Continue anyway?',
                'submit': 'You must upload a file or record a webcam video!',
                'webcam:showcurrent': 'Show current webcam recording',
                'webcam:recordnew': 'Record new video',
                'webcam:confirmlose': 'The webcam you recorded will be lost. Continue anyway?',
                'audio:showcurrent': 'Listen to current audio recording',
                'audio:recordnew': 'Record new audio',
                'audio:confirmlose': 'The audio you recorded will be lost. Continue anyway?'
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
        it('should exist', function () {
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
        it('should exist', function () {
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
        var spy,
            pickRandomMediaTypeExcept;

        beforeEach(function () {
            spy = spyOn(windowMock, 'confirm');
            pickRandomMediaTypeExcept = function (type) {
                var a = ['file', 'webcam', 'audio'];
                var index = a.indexOf(type);
                if (index === -1) {
                    return null;
                }
                a.splice(index, 1);
                return a[Math.random() < 0.5 ? 0 : 1];
            };
        });

        it('should exist', function () {
            expect(angular.isFunction(scope.changeMediaType)).toBeTruthy();
        });

        describe('when changing media type on a new talkpoint', function () {
            it('should show a confirmation after having uploaded a file', function () {
                var currentMediaType = 'file';
                scope.mediaType = currentMediaType;
                scope.uploadedfile = 'file.png';
                scope.title = 'My new talkpoint';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded webcam', function () {
                var currentMediaType = 'webcam';
                scope.mediaType = currentMediaType;
                scope.nimbbguid = 'ABC123';
                scope.title = 'My new talkpoint';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded audio', function () {
                var currentMediaType = 'audio';
                scope.mediaType = currentMediaType;
                scope.nimbbguid = 'ABC123';
                scope.title = 'My new talkpoint';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should not show a confirmation if no file has been uploaded', function () {
                var currentMediaType = 'file';
                scope.mediaType = currentMediaType;
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no webcam has been recorded', function () {
                var currentMediaType = 'webcam';
                scope.mediaType = currentMediaType;
                scope.title = 'My new talkpoint';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no audio has been recorded', function () {
                var currentMediaType = 'audio';
                scope.mediaType = currentMediaType;
                scope.title = 'My new talkpoint';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });
        });

        describe('when changing media type on an existing talkpoint', function () {
            it('should show a confirmation after having uploaded a (new) file', function () {
                var currentMediaType = 'file';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.uploadedfile = 'old.png';
                scope.uploadedfile = 'new.png';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having uploaded a file (when the original media type was anything other than "file")', function () {
                var currentMediaType = 'file';
                configMock.mediaType = 'webcam';
                configMock.nimbbguid = 'OLD123';
                scope.mediaType = currentMediaType;
                scope.uploadedfile = 'new.png';
                scope.changeMediaType(pickRandomMediaTypeExcept('currentMediaType'));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded a (new) webcam', function () {
                var currentMediaType = 'webcam';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.nimbbguid = 'OLD123';
                scope.nimbbguid = 'NEW123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded a webcam (when the original media type was anything other than "webcam")', function () {
                var currentMediaType = 'webcam';
                configMock.mediaType = 'file';
                configMock.uploadedfile = 'old.png';
                scope.mediaType = currentMediaType;
                scope.nimbbguid = 'ABC123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded (new) audio', function () {
                var currentMediaType = 'audio';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.nimbbguid = 'OLD123';
                scope.nimbbguid = 'NEW123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should show a confirmation after having recorded audio (when the original media type was anything other than "audio")', function () {
                var currentMediaType = 'audio';
                configMock.mediaType = 'webcam';
                configMock.nimbbguid = 'OLDWEBCAM123';
                scope.mediaType = currentMediaType;
                scope.nimbbguid = 'NEWAUDIO123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).toHaveBeenCalledWith(configMock.messages[currentMediaType + ':confirmlose']);
            });

            it('should not show a confirmation if no (new) file has been uploaded', function () {
                var currentMediaType = 'file';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.uploadedfile = scope.uploadedfile = 'new.png';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no (new) file has been uploaded (when the original media type was anything other than "file")', function () {
                var currentMediaType = 'file';
                configMock.mediaType = 'webcam';
                configMock.nimbbguid = 'OLD123';
                scope.mediaType = currentMediaType;
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no (new) webcam has been recorded', function () {
                var currentMediaType = 'webcam';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.nimbbguid = scope.nimbbguid = 'ABC123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no (new) webcam has been recorded (when the original media type was anything other than "webcam")', function () {
                var currentMediaType = 'webcam';
                configMock.mediaType = 'file';
                configMock.uploadedfile = 'old.png';
                scope.mediaType = currentMediaType;
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no (new) audio has been recorded', function () {
                var currentMediaType = 'audio';
                configMock.mediaType = scope.mediaType = currentMediaType;
                configMock.nimbbguid = scope.nimbbguid = 'ABC123';
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });

            it('should not show a confirmation if no (new) audio has been recorded (when the original media type was anything other than "audio")', function () {
                var currentMediaType = 'audio';
                configMock.mediaType = 'file';
                configMock.uploadedfile = 'old.png';
                scope.mediaType = currentMediaType;
                scope.changeMediaType(pickRandomMediaTypeExcept(currentMediaType));
                expect(windowMock.confirm).not.toHaveBeenCalled();
            });
        });

        it('should empty nimbbguid and uploadedfile', function () {
            spy.and.returnValue(true);
            configMock.mediaType = scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.uploadedfile = '';
            scope.changeMediaType('file');
            expect(scope.nimbbguid).toBe('');
            expect(scope.uploadedfile).toBe('');
        });

        it('should change media type', function () {
            spy.and.returnValue(true);
            configMock.mediaType = scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.changeMediaType('file');
            expect(scope.mediaType).toBe('file');
        });

        it('should set showCurrentRecording to false', function () {
            spy.and.returnValue(true);
            configMock.mediaType = scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.showCurrentRecording = true;
            scope.changeMediaType('file');
            expect(scope.showCurrentRecording).toBeFalsy();
        });

        it('should empty uploadError', function () {
            spy.and.returnValue(true);
            configMock.mediaType = scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.showCurrentRecording = true;
            scope.uploadError = 'Some random error';
            scope.changeMediaType('file');
            expect(scope.uploadError).toBe('');
        });

        it('should set uploadProgress to 0', function () {
            spy.and.returnValue(true);
            configMock.mediaType = scope.mediaType = 'webcam';
            scope.nimbbguid = 'ABC123';
            scope.showCurrentRecording = true;
            scope.uploadProgress = 100;
            scope.changeMediaType('file');
            expect(scope.uploadProgress).toBe(0);
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

    describe('method cancelChanges', function () {
        var spy;

        beforeEach(function () {
            spy = spyOn(scope, 'changeMediaType');
        });

        it('should exist', function () {
            expect(angular.isFunction(scope.cancelChanges)).toBeTruthy();
        });

        it('should call changeMediaType method with config.mediaType', function () {
            scope.cancelChanges();
            expect(spy).toHaveBeenCalledWith(configMock.mediaType);
        });

        it('should change scope.nimbbguid to config.nimbbguid', function () {
            configMock.nimbbguid = 'ABC123';
            scope.nimbbguid = 'DEF456';
            scope.cancelChanges();
            expect(scope.nimbbguid).toBe('ABC123');
        });

        it('should change scope.uploadedfile to config.uploadedfile', function () {
            configMock.uploadedfile = 'file.jpg';
            scope.uploadedfile = 'anotherfile.pdf';
            scope.cancelChanges();
            expect(scope.uploadedfile).toBe('file.jpg');
        });

    });

    describe('method isCancelButtonDisabled', function () {

        it('should exist', function () {
            expect(angular.isFunction(scope.isCancelButtonDisabled)).toBeTruthy();
        });

        it('should return true when config.mediaType equals scope.mediaType', function () {
            configMock.mediaType = scope.mediaType = 'file';
            expect(scope.isCancelButtonDisabled()).toBeTruthy();
        });

        it('should return false when config.mediaType does not equal scope.mediaType', function () {
            configMock.mediaType = 'audio';
            scope.mediaType = 'file';
            expect(scope.isCancelButtonDisabled()).toBeFalsy();
        });
    })

});
