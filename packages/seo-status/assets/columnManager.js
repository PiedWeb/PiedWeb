const queryString = require('query-string');

export const columnManager = class {
  constructor(prefix = '$') {
    this.prefix = prefix;
    this.defaultValue = {};
  }

  getParsedHashed() {
    return queryString.parse(location.hash);
  }

  getParam(name) {
    return this.getParsedHashed()[this.prefix + name];
  }

  setDefaultValue(name, value) {
    this.defaultValue[name] = value;

    return this;
  }

  isShown(name) {
    var value = this.getParam(name) ?? this.defaultValue[name];
    return value == '1' ? 1 : 0;
  }

  toggleVisibility(name) {
    var parsedHash = this.getParsedHashed();
    var value = this.getParam(name) ?? this.defaultValue[name];
    parsedHash[this.prefix + name] = value == '1' ? 0 : 1;

    if (this.defaultValue[name] == parsedHash[this.prefix + name]) {
      delete parsedHash[this.prefix + name];
    }

    history.pushState(null, null, '#' + queryString.stringify(parsedHash));
  }
};
