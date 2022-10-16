"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["app"],{

/***/ "./assets/app.js":
/*!***********************!*\
  !*** ./assets/app.js ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _fortawesome_fontawesome_free_js_fontawesome__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @fortawesome/fontawesome-free/js/fontawesome */ "./node_modules/@fortawesome/fontawesome-free/js/fontawesome.js");
/* harmony import */ var _fortawesome_fontawesome_free_js_fontawesome__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_fortawesome_fontawesome_free_js_fontawesome__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _fortawesome_fontawesome_free_js_solid__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @fortawesome/fontawesome-free/js/solid */ "./node_modules/@fortawesome/fontawesome-free/js/solid.js");
/* harmony import */ var _fortawesome_fontawesome_free_js_solid__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_fortawesome_fontawesome_free_js_solid__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _fortawesome_fontawesome_free_js_regular__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @fortawesome/fontawesome-free/js/regular */ "./node_modules/@fortawesome/fontawesome-free/js/regular.js");
/* harmony import */ var _fortawesome_fontawesome_free_js_regular__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_fortawesome_fontawesome_free_js_regular__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _fortawesome_fontawesome_free_js_brands__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @fortawesome/fontawesome-free/js/brands */ "./node_modules/@fortawesome/fontawesome-free/js/brands.js");
/* harmony import */ var _fortawesome_fontawesome_free_js_brands__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_fortawesome_fontawesome_free_js_brands__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _columnManager_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./columnManager.js */ "./assets/columnManager.js");
/* harmony import */ var _filtersManager_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./filtersManager.js */ "./assets/filtersManager.js");
/* harmony import */ var alpinejs__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! alpinejs */ "./node_modules/alpinejs/dist/module.esm.js");
/* harmony import */ var _ryangjchandler_alpine_tooltip__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @ryangjchandler/alpine-tooltip */ "./node_modules/@ryangjchandler/alpine-tooltip/dist/module.esm.js");
/* harmony import */ var chart_js_auto__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! chart.js/auto */ "./node_modules/chart.js/auto/auto.mjs");





window.columnManager = _columnManager_js__WEBPACK_IMPORTED_MODULE_4__.columnManager;

window.filtersManager = _filtersManager_js__WEBPACK_IMPORTED_MODULE_5__.filtersManager;


alpinejs__WEBPACK_IMPORTED_MODULE_6__["default"].plugin(_ryangjchandler_alpine_tooltip__WEBPACK_IMPORTED_MODULE_7__["default"]);
window.Alpine = alpinejs__WEBPACK_IMPORTED_MODULE_6__["default"];
alpinejs__WEBPACK_IMPORTED_MODULE_6__["default"].start();

window.Chart = chart_js_auto__WEBPACK_IMPORTED_MODULE_8__["default"];

/***/ }),

/***/ "./assets/columnManager.js":
/*!*********************************!*\
  !*** ./assets/columnManager.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "columnManager": () => (/* binding */ columnManager)
/* harmony export */ });
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }

var queryString = __webpack_require__(/*! query-string */ "./node_modules/query-string/index.js");

var columnManager = /*#__PURE__*/function () {
  function columnManager() {
    var prefix = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : '$';

    _classCallCheck(this, columnManager);

    this.prefix = prefix;
    this.defaultValue = {};
  }

  _createClass(columnManager, [{
    key: "getParsedHashed",
    value: function getParsedHashed() {
      return queryString.parse(location.hash);
    }
  }, {
    key: "getParam",
    value: function getParam(name) {
      return this.getParsedHashed()[this.prefix + name];
    }
  }, {
    key: "setDefaultValue",
    value: function setDefaultValue(name, value) {
      this.defaultValue[name] = value;
      return this;
    }
  }, {
    key: "isShown",
    value: function isShown(name) {
      var _this$getParam;

      var value = (_this$getParam = this.getParam(name)) !== null && _this$getParam !== void 0 ? _this$getParam : this.defaultValue[name];
      return value == '1' ? 1 : 0;
    }
  }, {
    key: "toggleVisibility",
    value: function toggleVisibility(name) {
      var _this$getParam2;

      var parsedHash = this.getParsedHashed();
      var value = (_this$getParam2 = this.getParam(name)) !== null && _this$getParam2 !== void 0 ? _this$getParam2 : this.defaultValue[name];
      parsedHash[this.prefix + name] = value == '1' ? 0 : 1;

      if (this.defaultValue[name] == parsedHash[this.prefix + name]) {
        delete parsedHash[this.prefix + name];
      }

      history.pushState(null, null, '#' + queryString.stringify(parsedHash));
    }
  }]);

  return columnManager;
}();

/***/ }),

/***/ "./assets/filtersManager.js":
/*!**********************************!*\
  !*** ./assets/filtersManager.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "filtersManager": () => (/* binding */ filtersManager)
/* harmony export */ });
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

var filtersManager = /*#__PURE__*/function () {
  function filtersManager(filters, url) {
    var _this$filters$where;

    _classCallCheck(this, filtersManager);

    _defineProperty(this, "operatorList", ['NOT LIKE', 'LIKE', '<>', '!=', '>=', '<=', '<', '>', '=', '!']);

    this.filters = JSON.parse(filters);
    this.filters.where = Object.assign({}, (_this$filters$where = this.filters.where) !== null && _this$filters$where !== void 0 ? _this$filters$where : {});
    this.url = url;
  }

  _createClass(filtersManager, [{
    key: "validate",
    value: function validate() {
      var encodedFilters = btoa(encodeURIComponent(JSON.stringify(this.filters)));
      var urlToRedirect = this.url.replace('filtersValue', encodedFilters);
      window.location.href = urlToRedirect;
    }
  }, {
    key: "toggleOrderBy",
    value: function toggleOrderBy(orderKey, orderValueToToggle) {
      if (this.filters.orderBy[orderKey] === orderValueToToggle) {
        delete this.filters.orderBy[orderKey];
        return this;
      }

      if (this.filters.orderBy[orderKey]) delete this.filters.orderBy[orderKey];
      var orderByToAdd = {};
      orderByToAdd[orderKey] = orderValueToToggle;
      this.filters.orderBy = _objectSpread(_objectSpread({}, orderByToAdd), this.filters.orderBy);
      return this;
    }
  }, {
    key: "update",
    value: function update(key, val) {
      var operator = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : '=';

      if (val === '') {
        delete this.filters.where[key];
        return this;
      }

      this.filters.where[key] = {
        k: key,
        o: operator,
        v: '' + val
      };
      return this;
    }
  }, {
    key: "updateInput",
    value: function updateInput(key) {
      var _element$getAttribute;

      var element = document.querySelector('input[name="' + key + '"],select[name="' + key + '"]');
      var val = element.value;
      var defaultOperator = (_element$getAttribute = element.getAttribute('operator')) !== null && _element$getAttribute !== void 0 ? _element$getAttribute : '=';

      if (val === '') {
        delete this.filters.where[key];
        return this;
      }

      this.filters.where[key] = this.parseInputSearchValue(key, val, defaultOperator);
      return this;
    }
  }, {
    key: "parseInputSearchValue",
    value: function parseInputSearchValue(key, val, defaultOperator) {
      var operator = '';
      this.operatorList.forEach(function (operatorToFind) {
        if (val.startsWith(operatorToFind)) {
          operator = operator === '' ? operatorToFind : operator;
        }
      });
      val = val.slice(operator.length);
      return {
        k: key,
        o: operator === '' ? defaultOperator : operator,
        v: val.trim()
      };
    }
  }]);

  return filtersManager;
}();

/***/ })

},
/******/ __webpack_require__ => { // webpackRuntimeModules
/******/ var __webpack_exec__ = (moduleId) => (__webpack_require__(__webpack_require__.s = moduleId))
/******/ __webpack_require__.O(0, ["vendors-node_modules_fortawesome_fontawesome-free_js_brands_js-node_modules_fortawesome_fonta-9855ef"], () => (__webpack_exec__("./assets/app.js")));
/******/ var __webpack_exports__ = __webpack_require__.O();
/******/ }
]);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,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UUFDbEQsSUFBSWIsR0FBRyxDQUFDYyxVQUFKLENBQWVELGNBQWYsQ0FBSixFQUFvQztVQUNsQ1osUUFBUSxHQUFHQSxRQUFRLEtBQUssRUFBYixHQUFrQlksY0FBbEIsR0FBbUNaLFFBQTlDO1FBQ0Q7TUFDRixDQUpEO01BS0FELEdBQUcsR0FBR0EsR0FBRyxDQUFDZSxLQUFKLENBQVVkLFFBQVEsQ0FBQ2UsTUFBbkIsQ0FBTjtNQUNBLE9BQU87UUFDTGQsQ0FBQyxFQUFFSCxHQURFO1FBRUxJLENBQUMsRUFBRUYsUUFBUSxLQUFLLEVBQWIsR0FBa0JPLGVBQWxCLEdBQW9DUCxRQUZsQztRQUdMRyxDQUFDLEVBQUVKLEdBQUcsQ0FBQ2lCLElBQUo7TUFIRSxDQUFQO0lBS0Q7RUF0RXdCOztFQUFBO0FBQUEsR0FBcEIiLCJzb3VyY2VzIjpbIndlYnBhY2s6Ly8vLi9hc3NldHMvYXBwLmpzIiwid2VicGFjazovLy8uL2Fzc2V0cy9jb2x1bW5NYW5hZ2VyLmpzIiwid2VicGFjazovLy8uL2Fzc2V0cy9maWx0ZXJzTWFuYWdlci5qcyJdLCJzb3VyY2VzQ29udGVudCI6WyJpbXBvcnQgJ0Bmb3J0YXdlc29tZS9mb250YXdlc29tZS1mcmVlL2pzL2ZvbnRhd2Vzb21lJztcbmltcG9ydCAnQGZvcnRhd2Vzb21lL2ZvbnRhd2Vzb21lLWZyZWUvanMvc29saWQnO1xuaW1wb3J0ICdAZm9ydGF3ZXNvbWUvZm9udGF3ZXNvbWUtZnJlZS9qcy9yZWd1bGFyJztcbmltcG9ydCAnQGZvcnRhd2Vzb21lL2ZvbnRhd2Vzb21lLWZyZWUvanMvYnJhbmRzJztcblxuaW1wb3J0IHsgY29sdW1uTWFuYWdlciB9IGZyb20gJy4vY29sdW1uTWFuYWdlci5qcyc7XG53aW5kb3cuY29sdW1uTWFuYWdlciA9IGNvbHVtbk1hbmFnZXI7XG5cbmltcG9ydCB7IGZpbHRlcnNNYW5hZ2VyIH0gZnJvbSAnLi9maWx0ZXJzTWFuYWdlci5qcyc7XG53aW5kb3cuZmlsdGVyc01hbmFnZXIgPSBmaWx0ZXJzTWFuYWdlcjtcblxuaW1wb3J0IEFscGluZSBmcm9tICdhbHBpbmVqcyc7XG5pbXBvcnQgVG9vbHRpcCBmcm9tICdAcnlhbmdqY2hhbmRsZXIvYWxwaW5lLXRvb2x0aXAnO1xuQWxwaW5lLnBsdWdpbihUb29sdGlwKTtcbndpbmRvdy5BbHBpbmUgPSBBbHBpbmU7XG5BbHBpbmUuc3RhcnQoKTtcblxuaW1wb3J0IENoYXJ0IGZyb20gJ2NoYXJ0LmpzL2F1dG8nO1xuXG53aW5kb3cuQ2hhcnQgPSBDaGFydDtcbiIsImNvbnN0IHF1ZXJ5U3RyaW5nID0gcmVxdWlyZSgncXVlcnktc3RyaW5nJyk7XG5cbmV4cG9ydCBjb25zdCBjb2x1bW5NYW5hZ2VyID0gY2xhc3Mge1xuICBjb25zdHJ1Y3RvcihwcmVmaXggPSAnJCcpIHtcbiAgICB0aGlzLnByZWZpeCA9IHByZWZpeDtcbiAgICB0aGlzLmRlZmF1bHRWYWx1ZSA9IHt9O1xuICB9XG5cbiAgZ2V0UGFyc2VkSGFzaGVkKCkge1xuICAgIHJldHVybiBxdWVyeVN0cmluZy5wYXJzZShsb2NhdGlvbi5oYXNoKTtcbiAgfVxuXG4gIGdldFBhcmFtKG5hbWUpIHtcbiAgICByZXR1cm4gdGhpcy5nZXRQYXJzZWRIYXNoZWQoKVt0aGlzLnByZWZpeCArIG5hbWVdO1xuICB9XG5cbiAgc2V0RGVmYXVsdFZhbHVlKG5hbWUsIHZhbHVlKSB7XG4gICAgdGhpcy5kZWZhdWx0VmFsdWVbbmFtZV0gPSB2YWx1ZTtcblxuICAgIHJldHVybiB0aGlzO1xuICB9XG5cbiAgaXNTaG93bihuYW1lKSB7XG4gICAgdmFyIHZhbHVlID0gdGhpcy5nZXRQYXJhbShuYW1lKSA/PyB0aGlzLmRlZmF1bHRWYWx1ZVtuYW1lXTtcbiAgICByZXR1cm4gdmFsdWUgPT0gJzEnID8gMSA6IDA7XG4gIH1cblxuICB0b2dnbGVWaXNpYmlsaXR5KG5hbWUpIHtcbiAgICB2YXIgcGFyc2VkSGFzaCA9IHRoaXMuZ2V0UGFyc2VkSGFzaGVkKCk7XG4gICAgdmFyIHZhbHVlID0gdGhpcy5nZXRQYXJhbShuYW1lKSA/PyB0aGlzLmRlZmF1bHRWYWx1ZVtuYW1lXTtcbiAgICBwYXJzZWRIYXNoW3RoaXMucHJlZml4ICsgbmFtZV0gPSB2YWx1ZSA9PSAnMScgPyAwIDogMTtcblxuICAgIGlmICh0aGlzLmRlZmF1bHRWYWx1ZVtuYW1lXSA9PSBwYXJzZWRIYXNoW3RoaXMucHJlZml4ICsgbmFtZV0pIHtcbiAgICAgIGRlbGV0ZSBwYXJzZWRIYXNoW3RoaXMucHJlZml4ICsgbmFtZV07XG4gICAgfVxuXG4gICAgaGlzdG9yeS5wdXNoU3RhdGUobnVsbCwgbnVsbCwgJyMnICsgcXVlcnlTdHJpbmcuc3RyaW5naWZ5KHBhcnNlZEhhc2gpKTtcbiAgfVxufTtcbiIsImV4cG9ydCBjb25zdCBmaWx0ZXJzTWFuYWdlciA9IGNsYXNzIHtcbiAgb3BlcmF0b3JMaXN0ID0gWydOT1QgTElLRScsICdMSUtFJywgJzw+JywgJyE9JywgJz49JywgJzw9JywgJzwnLCAnPicsICc9JywgJyEnXTtcblxuICBjb25zdHJ1Y3RvcihmaWx0ZXJzLCB1cmwpIHtcbiAgICB0aGlzLmZpbHRlcnMgPSBKU09OLnBhcnNlKGZpbHRlcnMpO1xuICAgIHRoaXMuZmlsdGVycy53aGVyZSA9IE9iamVjdC5hc3NpZ24oe30sIHRoaXMuZmlsdGVycy53aGVyZSA/PyB7fSk7XG4gICAgdGhpcy51cmwgPSB1cmw7XG4gIH1cblxuICB2YWxpZGF0ZSgpIHtcbiAgICB2YXIgZW5jb2RlZEZpbHRlcnMgPSBidG9hKGVuY29kZVVSSUNvbXBvbmVudChKU09OLnN0cmluZ2lmeSh0aGlzLmZpbHRlcnMpKSk7XG4gICAgdmFyIHVybFRvUmVkaXJlY3QgPSB0aGlzLnVybC5yZXBsYWNlKCdmaWx0ZXJzVmFsdWUnLCBlbmNvZGVkRmlsdGVycyk7XG4gICAgd2luZG93LmxvY2F0aW9uLmhyZWYgPSB1cmxUb1JlZGlyZWN0O1xuICB9XG5cbiAgdG9nZ2xlT3JkZXJCeShvcmRlcktleSwgb3JkZXJWYWx1ZVRvVG9nZ2xlKSB7XG4gICAgaWYgKHRoaXMuZmlsdGVycy5vcmRlckJ5W29yZGVyS2V5XSA9PT0gb3JkZXJWYWx1ZVRvVG9nZ2xlKSB7XG4gICAgICBkZWxldGUgdGhpcy5maWx0ZXJzLm9yZGVyQnlbb3JkZXJLZXldO1xuICAgICAgcmV0dXJuIHRoaXM7XG4gICAgfVxuXG4gICAgaWYgKHRoaXMuZmlsdGVycy5vcmRlckJ5W29yZGVyS2V5XSkgZGVsZXRlIHRoaXMuZmlsdGVycy5vcmRlckJ5W29yZGVyS2V5XTtcblxuICAgIGxldCBvcmRlckJ5VG9BZGQgPSB7fTtcbiAgICBvcmRlckJ5VG9BZGRbb3JkZXJLZXldID0gb3JkZXJWYWx1ZVRvVG9nZ2xlO1xuICAgIHRoaXMuZmlsdGVycy5vcmRlckJ5ID0geyAuLi5vcmRlckJ5VG9BZGQsIC4uLnRoaXMuZmlsdGVycy5vcmRlckJ5IH07XG5cbiAgICByZXR1cm4gdGhpcztcbiAgfVxuXG4gIHVwZGF0ZShrZXksIHZhbCwgb3BlcmF0b3IgPSAnPScpIHtcbiAgICBpZiAodmFsID09PSAnJykge1xuICAgICAgZGVsZXRlIHRoaXMuZmlsdGVycy53aGVyZVtrZXldO1xuICAgICAgcmV0dXJuIHRoaXM7XG4gICAgfVxuICAgIHRoaXMuZmlsdGVycy53aGVyZVtrZXldID0ge1xuICAgICAgazoga2V5LFxuICAgICAgbzogb3BlcmF0b3IsXG4gICAgICB2OiAnJyArIHZhbCxcbiAgICB9O1xuXG4gICAgcmV0dXJuIHRoaXM7XG4gIH1cblxuICB1cGRhdGVJbnB1dChrZXkpIHtcbiAgICBsZXQgZWxlbWVudCA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJ2lucHV0W25hbWU9XCInICsga2V5ICsgJ1wiXSxzZWxlY3RbbmFtZT1cIicgKyBrZXkgKyAnXCJdJyk7XG4gICAgbGV0IHZhbCA9IGVsZW1lbnQudmFsdWU7XG4gICAgbGV0IGRlZmF1bHRPcGVyYXRvciA9IGVsZW1lbnQuZ2V0QXR0cmlidXRlKCdvcGVyYXRvcicpID8/ICc9JztcbiAgICBpZiAodmFsID09PSAnJykge1xuICAgICAgZGVsZXRlIHRoaXMuZmlsdGVycy53aGVyZVtrZXldO1xuICAgICAgcmV0dXJuIHRoaXM7XG4gICAgfVxuXG4gICAgdGhpcy5maWx0ZXJzLndoZXJlW2tleV0gPSB0aGlzLnBhcnNlSW5wdXRTZWFyY2hWYWx1ZShrZXksIHZhbCwgZGVmYXVsdE9wZXJhdG9yKTtcbiAgICByZXR1cm4gdGhpcztcbiAgfVxuXG4gIHBhcnNlSW5wdXRTZWFyY2hWYWx1ZShrZXksIHZhbCwgZGVmYXVsdE9wZXJhdG9yKSB7XG4gICAgbGV0IG9wZXJhdG9yID0gJyc7XG4gICAgdGhpcy5vcGVyYXRvckxpc3QuZm9yRWFjaChmdW5jdGlvbiAob3BlcmF0b3JUb0ZpbmQpIHtcbiAgICAgIGlmICh2YWwuc3RhcnRzV2l0aChvcGVyYXRvclRvRmluZCkpIHtcbiAgICAgICAgb3BlcmF0b3IgPSBvcGVyYXRvciA9PT0gJycgPyBvcGVyYXRvclRvRmluZCA6IG9wZXJhdG9yO1xuICAgICAgfVxuICAgIH0pO1xuICAgIHZhbCA9IHZhbC5zbGljZShvcGVyYXRvci5sZW5ndGgpO1xuICAgIHJldHVybiB7XG4gICAgICBrOiBrZXksXG4gICAgICBvOiBvcGVyYXRvciA9PT0gJycgPyBkZWZhdWx0T3BlcmF0b3IgOiBvcGVyYXRvcixcbiAgICAgIHY6IHZhbC50cmltKCksXG4gICAgfTtcbiAgfVxufTtcbiJdLCJuYW1lcyI6WyJjb2x1bW5NYW5hZ2VyIiwid2luZG93IiwiZmlsdGVyc01hbmFnZXIiLCJBbHBpbmUiLCJUb29sdGlwIiwicGx1Z2luIiwic3RhcnQiLCJDaGFydCIsInF1ZXJ5U3RyaW5nIiwicmVxdWlyZSIsInByZWZpeCIsImRlZmF1bHRWYWx1ZSIsInBhcnNlIiwibG9jYXRpb24iLCJoYXNoIiwibmFtZSIsImdldFBhcnNlZEhhc2hlZCIsInZhbHVlIiwiZ2V0UGFyYW0iLCJwYXJzZWRIYXNoIiwiaGlzdG9yeSIsInB1c2hTdGF0ZSIsInN0cmluZ2lmeSIsImZpbHRlcnMiLCJ1cmwiLCJKU09OIiwid2hlcmUiLCJPYmplY3QiLCJhc3NpZ24iLCJlbmNvZGVkRmlsdGVycyIsImJ0b2EiLCJlbmNvZGVVUklDb21wb25lbnQiLCJ1cmxUb1JlZGlyZWN0IiwicmVwbGFjZSIsImhyZWYiLCJvcmRlcktleSIsIm9yZGVyVmFsdWVUb1RvZ2dsZSIsIm9yZGVyQnkiLCJvcmRlckJ5VG9BZGQiLCJrZXkiLCJ2YWwiLCJvcGVyYXRvciIsImsiLCJvIiwidiIsImVsZW1lbnQiLCJkb2N1bWVudCIsInF1ZXJ5U2VsZWN0b3IiLCJkZWZhdWx0T3BlcmF0b3IiLCJnZXRBdHRyaWJ1dGUiLCJwYXJzZUlucHV0U2VhcmNoVmFsdWUiLCJvcGVyYXRvckxpc3QiLCJmb3JFYWNoIiwib3BlcmF0b3JUb0ZpbmQiLCJzdGFydHNXaXRoIiwic2xpY2UiLCJsZW5ndGgiLCJ0cmltIl0sInNvdXJjZVJvb3QiOiIifQ==