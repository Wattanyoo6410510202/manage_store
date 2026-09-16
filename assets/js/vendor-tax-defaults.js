(function (root, factory) {
  if (typeof module === "object" && module.exports) {
    module.exports = factory();
  } else {
    root.VendorTaxDefaults = factory();
  }
})(typeof self !== "undefined" ? self : this, function () {
  "use strict";

  var DEFAULTS = {
    entityType: "juristic",
    vatMode: "none",
    whtEnabled: false,
    whtPercent: 3,
  };

  function firstValue(source, camelKey, snakeKey) {
    if (!source || typeof source !== "object") return undefined;
    return source[camelKey] !== undefined ? source[camelKey] : source[snakeKey];
  }

  function normalizeEntityType(value) {
    return value === "individual" || value === "juristic" ? value : DEFAULTS.entityType;
  }

  function normalizeVatMode(value) {
    return value === "none" || value === "exclusive" || value === "inclusive"
      ? value
      : DEFAULTS.vatMode;
  }

  function normalizeBoolean(value) {
    return value === true || value === 1 || value === "1" || value === "true" || value === "yes" || value === "on";
  }

  function normalizePercent(value) {
    var number = NaN;
    if (typeof value === "number") {
      number = value;
    } else if (typeof value === "string" && value.trim() !== "") {
      number = Number(value);
    }
    if (!Number.isFinite(number)) return DEFAULTS.whtPercent;
    return Math.min(100, Math.max(0, number));
  }

  function fromDataset(dataset) {
    return {
      entityType: normalizeEntityType(firstValue(dataset, "entityType", "entity_type")),
      vatMode: normalizeVatMode(firstValue(dataset, "vatMode", "default_vat_mode")),
      whtEnabled: normalizeBoolean(firstValue(dataset, "whtEnabled", "default_wht_enabled")),
      whtPercent: normalizePercent(firstValue(dataset, "whtPercent", "default_wht_percent")),
    };
  }

  function toControlState(defaults) {
    var normalized = fromDataset(defaults);
    return {
      vatEnabled: normalized.vatMode !== "none",
      vatIncluded: normalized.vatMode === "inclusive",
      whtEnabled: normalized.whtEnabled,
      whtPercent: normalized.whtPercent,
    };
  }

  function applyToProjectForm(document, defaults) {
    var state = toControlState(defaults);
    var vatToggle = document && document.getElementById("vat_toggle");
    var vatIncludeCheck = document && document.getElementById("vat_include_check");
    var whtToggle = document && document.getElementById("wht_toggle");
    var whtPercent = document && document.getElementById("wht_percent");
    var whtPercentLabel = document && document.getElementById("wht_percent_label");

    if (vatToggle) vatToggle.checked = state.vatEnabled;
    if (vatIncludeCheck) vatIncludeCheck.checked = state.vatIncluded;
    if (whtToggle) whtToggle.checked = state.whtEnabled;
    if (whtPercent) whtPercent.value = String(state.whtPercent);
    if (whtPercentLabel) {
      whtPercentLabel.innerText = String(state.whtPercent);
      if ("textContent" in whtPercentLabel) whtPercentLabel.textContent = String(state.whtPercent);
    }
  }

  return {
    fromDataset: fromDataset,
    toControlState: toControlState,
    applyToProjectForm: applyToProjectForm,
  };
});
