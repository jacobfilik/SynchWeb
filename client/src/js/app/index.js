var Styles = require('css/main.scss')
var FontAwesome = require('font-awesome/css/font-awesome.css')

import Vue from 'vue'
import VeeValidate from 'vee-validate'
import PortalVue from 'portal-vue'

import Main from 'app/layouts/main.vue'
import MaintenanceView from 'app/layouts/maintenance.vue'
import store from 'app/store/store'
import router from 'app/router/router'

import MarionetteApp from 'app/marionette-application.js'

import config from 'config.json'
import VeeValidateCustomRules from "js/app/mixins/vee-validate-custom-rules";

Vue.use(VeeValidate)
Vue.use(PortalVue)

Vue.config.productionTip = false
Vue.config.devtools = !config.production


const vm = new Vue({
  store,
  router,
  render: function(h) {
    if (config.maintenance) return h(MaintenanceView, {props: {'message': config.maintenance_message}})
    else return h(Main)
  },
  created: function() {
    console.log("VUE::created")

    // Start the Marionette application
    let application = MarionetteApp.getInstance()

    application.start()
  },
  mixins: [VeeValidateCustomRules]
}).$mount('#synchweb-app')


// For testing purposes....
if (window.Cypress) {
  window.vm = vm
}

module.hot.accept()