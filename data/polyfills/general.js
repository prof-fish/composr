(function (){
    'use strict';
    // Credit for isSymbol and define: https://github.com/inexorabletash/polyfill/blob/master/es6.js
    function isSymbol(s) {
        return (typeof s === 'symbol') || (('Symbol' in window) && (Object.prototype.toString.call(s) === '[object Symbol]'));
    }

    function define(obj, prop, value, override) {
        var isFunc = typeof value === 'function';

        if ((prop in obj) && !override && !window.OVERRIDE_NATIVE_FOR_TESTING) {
            return;
        }

        if (isFunc) {
            // Sanity check that functions are appropriately named (where possible)
            console.assert(isSymbol(prop) || !('name' in value) || (value.name === prop) || (value.name === prop + '_'), 'Expected function name "' + prop.toString() + '", was "' + value.name + '"');
        }

        Object.defineProperty(obj, prop, {
            value: value,
            configurable: isFunc,
            enumerable: false,
            writable: isFunc
        });
    }

    // 20.1.2.6 Number.MAX_SAFE_INTEGER
    define(Number, 'MAX_SAFE_INTEGER', 9007199254740991); // 2^53-1

    // 20.1.2.8 Number.MIN_SAFE_INTEGER
    define(Number, 'MIN_SAFE_INTEGER', -9007199254740991); // -2^53+1

    // Credit: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/isNaN
    define(Number, 'isNaN', function (value) {
        return value !== value;
    });

    // Credit: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/isFinite
    define(Number, 'isFinite', function (value) {
        return (typeof value === 'number') && isFinite(value);
    });

    // Credit: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/isInteger
    define(Number, 'isInteger', function (value) {
        return Number.isFinite(value) && (Math.floor(value) === value);
    });

    // Credit: https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/includes
    define(String.prototype, 'includes', function (search, start) {
        if (typeof start !== 'number') {
            start = 0;
        }

        if (start + search.length > this.length) {
            return false;
        }

        return this.indexOf(search, start) !== -1;
    });

    // Credit: https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/startsWith
    define(String.prototype, 'startsWith', function (searchString, position) {
        position = position || 0;
        return this.substr(position, searchString.length) === searchString;
    });


    // https://developer.mozilla.org/en/docs/Web/JavaScript/Reference/Global_Objects/String/endsWith
    define(String.prototype, 'endsWith', function (searchString, position) {
        var subjectString = this.toString();
        if (typeof position !== 'number' || !isFinite(position) || Math.floor(position) !== position || position > subjectString.length) {
            position = subjectString.length;
        }
        position -= searchString.length;
        var lastIndex = subjectString.lastIndexOf(searchString, position);
        return lastIndex !== -1 && lastIndex === position;
    });

    // Credit: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/includes
    define(Array.prototype, 'includes', function (searchElement /*, fromIndex*/) {
        var isSearchNaN = Number.isNaN(searchElement); // Needs a special check since NaN !== NaN

        if ((this === null) || (this === undefined)) {
            throw new TypeError('Array.prototype.includes called with invalid context');
        }

        var O = Object(this);
        var len = parseInt(O.length, 10) || 0;
        if (len === 0) {
            return false;
        }
        var n = parseInt(arguments[1], 10) || 0;
        var k;
        if (n >= 0) {
            k = n;
        } else {
            k = len + n;
            if (k < 0) {
                k = 0;
            }
        }
        var currentElement;
        while (k < len) {
            currentElement = O[k];
            if ((searchElement === currentElement) || (isSearchNaN && Number.isNaN(currentElement))) {
                return true;
            }
            k++;
        }
        return false;
    });
}());