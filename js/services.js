'use strict';

var app = angular.module('talkpointsApp.services', []);

app.service('talkpointsSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.baseurl + config.api + '/talkpoint/' + config.instanceid;

        this.getPageOfTalkpoints = function (page, perPage) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.getTalkpoint = function (talkpointid) {
            var deferred = $q.defer();
            $http.get(url + '/' + talkpointid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };

        this.deleteTalkpoint = function (talkpointid) {
            var deferred = $q.defer();
            // workaround Moodle's JavaScript minifier breaking due to the 'delete' keyword
            var prop = 'delete';
            var f = $http[prop];
            f(url + '/' + talkpointid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };
    }
]);

app.service('commentsSrv', [
    '$http', '$q', 'CONFIG',
    function ($http, $q, config) {
        var url = config.baseurl + config.api + '/talkpoint/' + config.instanceid + '/' + config.talkpointid + '/comment';

        this.getPageOfComments = function (page, perPage) {
            var deferred = $q.defer();
            $http.get(url + '?limitfrom=' + (page * perPage) + '&limitnum=' + perPage).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
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
            $http.post(url, data).
                success(function (d) {
                    deferred.resolve(d);
                }).
                error(function (d) {
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
            $http.post(url, data).
                success(function (d) {
                    deferred.resolve(d);
                }).
                error(function (d) {
                    deferred.reject(d);
                });
            return deferred.promise;
        };

        this.putTextComment = function (commentid, textcomment) {
            var deferred = $q.defer();
            var data = {
                textcomment: textcomment
            };
            $http.put(url + '/' + commentid, data).
                success(function (d) {
                    deferred.resolve(d);
                }).
                error(function (d) {
                    deferred.reject(d);
                });
            return deferred.promise;
        };

        this.deleteComment = function (commentid) {
            var deferred = $q.defer();
            // workaround Moodle's JavaScript minifier breaking due to the 'delete' keyword
            var prop = 'delete';
            var f = $http[prop];
            f(url + '/' + commentid).
                success(function (data) {
                    deferred.resolve(data);
                }).
                error(function (data) {
                    deferred.reject(data);
                });
            return deferred.promise;
        };
    }
]);
