'use strict';

var app = angular.module('talkpointsApp', [
    'talkpointsApp.controllers',
    'talkpointsApp.services',
    'talkpointsApp.directives',
    'ngSanitize'
]);

app.config(['$sceDelegateProvider', function ($sceDelegateProvider) {
    $sceDelegateProvider.resourceUrlWhitelist([
        'self',
        'http://player.nimbb.com/**',
        'https://player.nimbb.com/**',
        'http://api.nimbb.com/**',
        'https://api.nimbb.com/**'
    ]);
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
