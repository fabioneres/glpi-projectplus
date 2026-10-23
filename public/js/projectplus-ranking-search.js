/* global CSS */
(function () {
   'use strict';

   var rankingSearchOptionIds = ['9520', '9524'];
   var operatorLabels = {
      morethan: 'maior que',
      lessthan: 'menor que'
   };

   function getRelatedName(fieldName, suffix) {
      return fieldName.replace(/\[field\]$/, '[' + suffix + ']');
   }

   function updateRankingCriteria() {
      var fields = document.querySelectorAll('select[name$="[field]"]');
      Array.prototype.forEach.call(fields, function (field) {
         if (rankingSearchOptionIds.indexOf(field.value) === -1) {
            return;
         }

         var searchType = document.querySelector('[name="' + getRelatedName(field.name, 'searchtype') + '"]');
         var value = document.querySelector('[name="' + getRelatedName(field.name, 'value') + '"]');
         if (searchType) {
            Array.prototype.forEach.call(searchType.options, function (option) {
               if (operatorLabels[option.value] && option.text !== operatorLabels[option.value]) {
                  option.text = operatorLabels[option.value];
               }
            });
         }
         if (value && value.tagName === 'INPUT') {
            value.type = 'text';
            value.pattern = '[0-9]*';
            value.inputMode = 'numeric';
         }
      });
   }

   document.addEventListener('change', updateRankingCriteria, true);
   new MutationObserver(updateRankingCriteria).observe(document.documentElement, {
      childList: true,
      subtree: true
   });
   updateRankingCriteria();
}());
