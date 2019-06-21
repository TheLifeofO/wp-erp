import Vue from 'vue'
import Datepicker from 'admin/components/base/Datepicker.vue'
import ListTable from 'admin/components/list-table/ListTable.vue'
import Dropdown from 'admin/components/base/Dropdown.vue'
import SendMail from 'admin/components/email/SendMail.vue'
import VueSweetalert2 from 'vue-sweetalert2'
import commonMixins from './mixins/common'
import Loading from 'vue-loading-overlay'
import HTTP from 'admin/http'
import FileUpload from 'admin/components/base/FileUpload.vue'
import ShowErrors from 'admin/components/base/ShowErrors.vue'
import SubmitButton from 'admin/components/base/SubmitButton.vue'
import MultiSelect from 'admin/components/select/MultiSelect.vue'
import SelectAccounts from 'admin/components/select/SelectAccounts.vue'
import TimePicker from 'admin/components/timepicker/TimePicker.vue'
import SelectPeople from 'admin/components/people/SelectPeople.vue'
import VeeValidate from 'vee-validate'
import DynamicTrnLoader from 'admin/components/transactions/DynamicTrnLoader.vue'
import { createHooks } from '@wordpress/hooks'


// global acct var
window.acct = {
    libs: {}
};


// assign libs to window for global use
window.acct.libs['Vue'] = Vue;
window.acct.libs['Datepicker'] = Datepicker;
window.acct.libs['ListTable'] = ListTable;
window.acct.libs['Dropdown'] = Dropdown;
window.acct.libs['SendMail'] = SendMail;
window.acct.libs['VueSweetalert2'] = VueSweetalert2;
window.acct.libs['commonMixins'] = commonMixins;
window.acct.libs['Loading'] = Loading;
window.acct.libs['HTTP'] = HTTP;
window.acct.libs['FileUpload'] = FileUpload;
window.acct.libs['ShowErrors'] = ShowErrors;
window.acct.libs['SubmitButton'] = SubmitButton;
window.acct.libs['MultiSelect'] = MultiSelect;
window.acct.libs['SelectAccounts'] = SelectAccounts;
window.acct.libs['ListTable'] = ListTable;
window.acct.libs['TimePicker'] = TimePicker;
window.acct.libs['SelectPeople'] = SelectPeople;
window.acct.libs['DynamicTrnLoader'] = DynamicTrnLoader;
window.acct.libs['VeeValidate'] = VeeValidate;


// get lib reference from window
window.acct_get_lib = function(lib) {
    return window.acct.libs[lib];
};


// hook manipulation
acct.hooks  = createHooks();

acct.addFilter = (hookName, namespace, component, priority = 10) => {
    acct.hooks.addFilter(hookName, namespace, (components) => {
        components.push(component);
        return components;
    }, priority );
};