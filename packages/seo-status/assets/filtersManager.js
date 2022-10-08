export const filtersManager = class {
  operatorList = ['NOT LIKE', 'LIKE', '<>', '!=', '>=', '<=', '<', '>', '=', '!'];

  constructor(filters, url) {
    this.filters = JSON.parse(filters);
    this.filters.where = this.filters.where ?? {};
    this.url = url;
  }

  validate() {
    var encodedFilters = btoa(encodeURIComponent(JSON.stringify(this.filters)));
    var urlToRedirect = this.url.replace('filtersValue', encodedFilters);
    window.location.href = urlToRedirect;
  }

  toggleOrderBy(orderKey, orderValueToToggle) {
    if (this.filters.orderBy[orderKey] === orderValueToToggle) {
      delete this.filters.orderBy[orderKey];
      return this;
    }

    if (this.filters.orderBy[orderKey]) delete this.filters.orderBy[orderKey];

    let orderByToAdd = {};
    orderByToAdd[orderKey] = orderValueToToggle;
    this.filters.orderBy = { ...orderByToAdd, ...this.filters.orderBy };

    return this;
  }

  update(key, val, operator = '=') {
    if (val === '') {
      delete this.filters.where[key];
      return this;
    }
    this.filters.where[key] = {
      k: key,
      o: operator,
      v: '' + val,
    };

    return this;
  }

  updateInput(key) {
    let element = document.querySelector('input[name="' + key + '"],select[name="' + key + '"]');
    let val = element.value;
    let defaultOperator = element.getAttribute('operator') ?? '=';
    if (val === '') {
      delete this.filters.where[key];
      return this;
    }

    this.filters.where[key] = this.parseInputSearchValue(key, val, defaultOperator);
    return this;
  }

  parseInputSearchValue(key, val, defaultOperator) {
    let operator = '';
    this.operatorList.forEach(function (operatorToFind) {
      if (val.startsWith(operatorToFind)) {
        operator = operator === '' ? operatorToFind : operator;
      }
    });
    val = val.slice(operator.length);
    return {
      k: key,
      o: operator === '' ? defaultOperator : operator,
      v: val.trim(),
    };
  }
};
