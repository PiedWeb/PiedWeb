export const filtersManager = class {
  operatorList = ['=', '>=', '<=', '<', '>', 'LIKE', '<>'];

  constructor(filters, url) {
    this.filters = JSON.parse(filters);
    this.url = url;
  }

  validate() {
    var encodedFilters = btoa(JSON.stringify(this.filters));
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

  update(key) {
    let element = document.querySelector('input[name="' + key + '"]');
    let val = element.value;
    let defaultOperator = element.getAttribute('operator') ?? '=';
    if (val === '') {
      delete this.filters[key];
      return this;
    }

    this.filters[key] = this.parseInputSearchValue(key, val, defaultOperator);
    return this;
  }

  parseInputSearchValue(key, val, defaultOperator) {
    let operator = '';
    this.operatorList.forEach(function (operatorToFind) {
      if (val.startsWith(operatorToFind)) {
        operator = operatoToFind;
      }
    });
    val = val.slice(operator.length);
    return {
      k: key,
      o: operator === '' ? defaultOperator : operator,
      v: val,
    };
  }
};
