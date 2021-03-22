<?php
if ( ! function_exists( 'is_plugin_active' ) ) {
    function is_plugin_active( $plugin ) {
        return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
    }
}
?><!DOCTYPE html>
<html>
<head>
	<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,900" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/@mdi/font@4.x/css/materialdesignicons.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.css" rel="stylesheet">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">
	<meta name="description" content="<?php echo localmeet_meta_description(); ?>" />
	<?php echo localmeet_header_content_extracted(); ?>
	<title>LocalMeet</title>
	<style>
		[v-cloak] > * {
			display:none;
		}
		.v-main, .v-navigation-drawer {
			transition: none;
		}
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>tbody>tr:not(:last-child)>td:last-child, 
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>tbody>tr:not(:last-child)>td:not(.v-data-table__mobile-row), 
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>tbody>tr:not(:last-child)>th:last-child, 
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>tbody>tr:not(:last-child)>th:not(.v-data-table__mobile-row),
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>thead>tr:last-child>th {
			border: 0px;
		}
		.theme--light.v-data-table.minimal>.v-data-table__wrapper>table>tbody>tr:hover:not(.v-data-table__expanded__content):not(.v-data-table__empty-wrapper) {
			background: none;
		}
		.event-content ul {
			padding-bottom: 16px;
		}
	</style>
</head>
<body>
<div id="app" server-rendered="true" v-cloak>
	<?php localmeet_content(); ?>
</div>
<div id="app-template" v-cloak>
<?php echo file_get_contents( plugin_dir_path(__FILE__) . "template.html" ) ?>
</div>
<?php if ( substr( $_SERVER['SERVER_NAME'], -9) == 'localhost' ) { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
<?php } else { ?>
<script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.min.js"></script>
<?php } ?>
<script src="https://cdn.jsdelivr.net/npm/axios@0.19.0/dist/axios.min.js"></script>
<script>
<?php if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { ?>
wc_countries = <?php $countries = ( new WC_Countries )->get_allowed_countries(); foreach ( $countries as $key => $county ) { $results[] = [ "text" => $county, "value" => $key ]; }; echo json_encode( $results ); ?>;
wc_states = <?php echo json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ); ?>;
wc_address_i18n_params = <?php echo json_encode( WC()->countries->get_country_locale() ); ?>;
<?php } else { ?>
wc_countries = []
wc_states = []
<?php } ?>
// Example: new Date("2018-06-18 19:44:47").toLocaleTimeString("en-us", options); Returns: "Monday, Jun 18, 2018, 7:44 PM"
var pretty_timestamp_options = {
    weekday: "short", year: "numeric", month: "short",
    day: "numeric", hour: "2-digit", minute: "2-digit"
}

var prerendered = document.querySelector('#app')
var rendered = document.querySelector('#app-template')

//rendered.innerHTML = html
prerendered.replaceWith( rendered )
rendered.id = "app"

new Vue({
	el: '#app',
	vuetify: new Vuetify({
		theme: {
			themes: {
				light: {
					primary: '#2849c5',
					secondary: '#424242',
					accent: '#82B1FF',
					error: '#FF5252',
					info: '#2196F3',
					success: '#4CAF50',
					warning: '#FFC107'
				},
			},
		},
	}),
	data: {
		countries: wc_countries,
		plugins_url: "<?php echo plugins_url(); ?>",
		drawer: null,
		logged_in: false,
		wp_nonce: "",
		attend_menu: "",
		attend_selection: "",
		event: {},
		new_event: { show: false, time: "", date: "", time_picker: false, date_selector: false, name: "", location: "", group_id: "", description: "" },
		edit_event: { show: false, time: "", date: "", time_picker: false, date_selector: false, errors: [], event: {} },
		attend_event: { show: false, event_id: "", first_name: "", last_name: "", email: "", errors: [] },
		edit_group: { show: false, errors: [], group: {} },
		group: {},
		group_nav: 1,
		groups: [],
		group_new: { errors: [], name: "", email: "" },
		group_apply: { address: { country: "US" }, type: "new" },
		group_search: "",
		login: { user_login: "", user_password: "", errors: "", loading: false, lost_password: false, message: "" },
		organization: {},
		page: "",
		snackbar: { show: false, message: "" },
		states: wc_states,
		states_selected: [],
		user: <?php echo json_encode( ( new LocalMeet\App )->current_user() ); ?>,
		route: "",
		route_path: "",
		routes: {
			'' : '',
			'/': '',
			'/profile': 'profile',
			'/sign-out': 'sign-out',
			'/start-group': 'start-group',
			'/find-group': 'find-group',
			'/account': 'account',
		},
	},
	mounted() {
		if ( typeof wpApiSettings == "undefined" ) {
			//window.history.pushState( {}, 'login', "/account/login" )
			this.logged_in = false
		} else {
			this.wp_nonce = wpApiSettings.nonce
			this.logged_in = true
		}
		window.addEventListener('popstate', () => {
			this.updateRoute( window.location.pathname )
		})
		this.updateRoute( window.location.pathname )
	},
	watch: {
		route() {
			this.triggerRoute()
		},
		route_path() {
			this.triggerPath()
		},
	},
	filters: {
		pretty_timestamp: function (date) {
			// takes in '2018-06-18 19:44:47' then returns "Monday, Jun 18, 2018, 7:44 PM"
			formatted_date = new Date(date).toLocaleTimeString("en-us", pretty_timestamp_options);
			return formatted_date;
		},
	},
	methods: {
		groupLink( page ) {
			if ( this.organization.slug ) {
				organization = this.organization.slug
			} else {
				organization = "group"
			}

			return `/${organization}/${this.group.slug}/${page}`
		},
		groupHomeLink( group ) {
			if ( group.organization_id == "0" ) {
				organization = "group"
			}
			return `/${organization}/${group.slug}`
		},
		updateRoute( href ) {
			page_depth = href.match(/\//g).length
			// Remove trailing slash
			if ( href.slice(-1) == "/" ) {
				href = href.slice(0, -1)
			}
			// Catch all nested routes to their parent route.
			if ( href != "" && href.match(/\//g).length > 1 ) {
				this.route_path = href.split('/').slice( 2 ).join( "/" )
				href = href.split('/').slice( 0, 2 ).join( "/" )
			} else {
				this.route_path = ""
			}
			this.route = this.routes[ href ]
			if ( typeof this.route == 'undefined' ) {
				
				if ( page_depth == 1 ) {
					this.route = "organization"
				}
				if ( page_depth == 2 ) {
					this.route = "group"
				}
				if ( page_depth == 3 ) {
					this.route = "event"
				}
			}
		},
		triggerRoute() {
			page_depth = window.location.pathname.match(/\//g).length
			organization = window.location.pathname.split('/').slice( 1, 2 ).join( "/" )
			group = window.location.pathname.split('/').slice( 2, 3 ).join( "/" )
			event = window.location.pathname.split('/').slice( 3, 4 ).join( "/" )
			if ( page_depth == 3 ) {
				this.page = window.location.pathname.split('/').slice( 0, 2 ).join( "/" )
			}
			if ( this.route == "" ) {
				this.group = {}
				this.organization = {}
			}
			if ( this.route == "profile" ) {
				this.group = {}
				this.organization = {}
			}
			if ( this.route == "sign-out" ) {
				this.signOut()
			}
			if ( this.route == "start-group" ) {
				this.group = {}
			}
			if ( this.route == "event" ) {
				this.fetchEvent()
				if ( ! this.group.slug ) {
					this.fetchGroup()
				}
				urlParams = new URLSearchParams(window.location.search)
				rsvp = urlParams.get('rsvp');
				if ( rsvp ) {
					this.snackbar.message = "You are confirmed."
					this.snackbar.show = true
				}
			}
			if ( this.route == "find-group" ) {
				this.fetchGroups()
				this.group = {}
				this.organization = {}
			}
			if ( this.route == "group" ) {
				this.group = {}
				if ( organization == "group" ) {
					this.organization = {}
				}
				if ( group == "start-group" ) {
					this.populateStates()
					this.group = {}
				}
				if ( group != "start-group" ) {
					this.fetchGroup()
				}
				if ( organization != "group" ) {
					this.fetchOrganization()
				}
			}
			if ( this.route == "organization" ) {
				this.fetchOrganization()
			}
		},
		triggerPath() {
			if ( this.route_path == "start-group" ) {
				this.group = {}
			}
		},
		goToPath ( href ) {
			this.updateRoute( href )
			window.history.pushState( {}, this.routes[href], href )
		},
		createGroup() {
			this.group_new.errors = []
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.post( '/wp-json/localmeet/v1/groups/create', {
				'request': this.group_new,
			},{
				headers: headers
			})
			.then( response => {
				if ( typeof response.data.errors === 'undefined' || response.data.errors.length == 0 ) {
					this.group_new = { errors: [], name: "", email: "" }
					this.snackbar.message = "Your group is almost ready. Check your email to complete verification."
					this.snackbar.show = true
					this.route = ""
					window.history.pushState( {}, 'LocalMeet', "/" )
					return
				}
				this.group_new.errors = response.data.errors
			})
			.catch(error => {
				console.log(error);
			});
		},
		fetchEvent() {
			organization = window.location.pathname.split('/').slice( 1, 2 ).join( "/" )
			group = window.location.pathname.split('/').slice( 2, 3 ).join( "/" )
			event = window.location.pathname.split('/').slice( 3, 4 ).join( "/" )
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.get( `/wp-json/localmeet/v1/event/${event}?organization=${organization}&group=${group}`, {
				headers: headers
			})
			.then(response => {
				this.event = response.data
			});
		},
		fetchGroups() {
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.get( `/wp-json/localmeet/v1/groups`, {
				headers: headers
			})
			.then(response => {
				this.groups = response.data
			});
		},
		fetchGroup() {
			organization = window.location.pathname.split('/').slice( 1 )[0]
			group = window.location.pathname.split('/').slice( 2, 3 ).join( "/" )
			if ( organization != "group" ) {
				group = `${group}?organization=${organization}`
			}
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.get( `/wp-json/localmeet/v1/group/${group}`, {
				headers: headers
			})
			.then(response => {
				this.group = response.data
			});
		},
		fetchOrganization() {
			organization = window.location.pathname.split('/').slice( 1, 2 ).join( "/" )
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.get( `/wp-json/localmeet/v1/organization/${organization}`, {
				headers: headers
			})
			.then(response => {
				this.organization = response.data
			}).catch(err => {
				this.route = "missing"
			})
		},
		addEvent() {
			this.new_event.group_id = this.group.group_id
			axios.post( '/wp-json/localmeet/v1/events/create', {
				'new_event': this.new_event
			},{
				headers: { 'X-WP-Nonce': this.wp_nonce }
			}).then( response => {
				this.fetchGroup()
				this.new_event = { show: false, time: "", date: "", time_picker: false, date_selector: false, name: "", location: "", group_id: "", description: "" }
			})
		},
		editEvent() {
			this.edit_event = { show: false, time: "", date: "", time_picker: false, date_selector: false, errors: [], event: {} }
			this.edit_event.event = JSON.parse ( JSON.stringify ( this.event ) )
			this.edit_event.date = this.edit_event.event.event_at.substr(0, 10)
			this.edit_event.time = this.edit_event.event.event_at.substr(11, 8)
		},
		updateEvent() {
			event_id = this.edit_event.event.event_id
			this.edit_event.event.event_at = `${this.edit_event.date} ${this.edit_event.time}`
			axios.post( `/wp-json/localmeet/v1/event/${event_id}/update`, {
				'edit_event': this.edit_event
			},{
				headers: { 'X-WP-Nonce': this.wp_nonce }
			}).then( response => {
				if ( response.data.errors ) {
					this.edit_event.errors = response.data.errors
					return
				}
				this.fetchEvent()
				this.edit_event = { show: false, time: "", date: "", time_picker: false, date_selector: false, errors: [], event: {} }
			})
		},
		deleteEvent() {
			proceed = confirm("Delete event?")
			if ( ! proceed ) {
				return
			}
			this.new_event.group_id = this.group.group_id
			axios.get( `/wp-json/localmeet/v1/event/${this.event.event_id}/delete`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			}).then( response => {
				this.fetchGroup()
				this.snackbar.message = "Event has been deleted."
				this.snackbar.show = true
				this.goToPath( this.groupHomeLink( this.group ) )
			})
		},
		attendEventRequest() {
			this.attend_event.event_id = this.event.event_id
			axios.post( `/wp-json/localmeet/v1/attendee/create`, {
				'request': this.attend_event
			}).then( response => {
				if ( response.data.errors ) {
					this.attend_event.errors = response.data.errors
					return
				}
				this.snackbar.message = "Please check your email to confirm."
				this.snackbar.show = true
				this.fetchEvent()
				this.attend_event = { attend_event: false, event_id: "", first_name: "", last_name: "", email: "", errors: [] }
			})
		},
		attendEvent() {
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			event_id = this.event.event_id
			axios.post( `/wp-json/localmeet/v1/event/${event_id}/attend`, {
				'selection': this.attend_selection
			},{
				headers: headers
			}).then( response => {
				this.attend_menu = false
				this.fetchEvent()
				this.attend_selection = ""
			})
		},
		editGroup() {
			this.edit_group = { show: false, errors: [], group: {} }
			this.edit_group.group = JSON.parse ( JSON.stringify ( this.group ) )
		},
		updateGroup() {
			group_id = this.edit_group.group.group_id
			axios.post( `/wp-json/localmeet/v1/group/${group_id}/update`, {
				'edit_group': this.edit_group
			},{
				headers: { 'X-WP-Nonce': this.wp_nonce }
			}).then( response => {
				if ( response.data.errors ) {
					this.edit_group.errors = response.data.errors
					return
				}
				this.fetchGroup()
				this.edit_group = { show: false, errors: [], group: {} }
			})
		},
		deleteGroup() {
			proceed = confirm("Delete group? Warning all past events will be deleted.")
			if ( ! proceed ) {
				return
			}
			axios.get( `/wp-json/localmeet/v1/group/${this.group.group_id}/delete`, {
				headers: { 'X-WP-Nonce': this.wp_nonce }
			}).then( response => {
				this.fetchGroups()
				this.edit_group = { show: false, errors: [], group: {} }
				this.snackbar.message = "Group has been deleted."
				this.snackbar.show = true
				this.goToPath( "/" )
			})
		},
		populateStates() {
			states_selected = []
			select = this.states[ this.group_apply.address.country ]
			if ( typeof select != 'object' ) {
				this.states_selected = []
				return
			}
			states_by_country = Object.entries( select )
			states_by_country.forEach( ([key, value]) => {
				states_selected.push( { "text": value, "value": key } )
			})
			this.states_selected = states_selected
		},
		updateAccount() {
			headers = {}
			if ( this.wp_nonce ) {
				headers['X-WP-Nonce'] = this.wp_nonce
			}
			axios.post( '/wp-json/localmeet/v1/login/', {
				'command': "updateAccount",
				'user': this.user
			},{
				headers: headers
			}).then( response => {
				if ( response.data.errors ) {
					this.snackbar.message = "Failed to update your account."
					this.snackbar.show = true
					this.user.errors = response.data.errors
					return
				}
				this.snackbar.message = "Account updated."
				this.snackbar.show = true
				this.user.name = response.data.user.name
				this.user.errors = []
				this.user.new_password = ""
			})
			.catch( error => console.log( error ) );
		},
		signIn() {
			this.login.loading = true
			if ( ! this.$refs.login.validate() ) {
				this.login.loading = false
				return
			}
			axios.post( '/wp-json/localmeet/v1/login/', {
					'command': "signIn",
					'login': this.login
				})
				.then( response => {
					if ( typeof response.data.errors === 'undefined' ) {
						window.location = window.location
						return
					}
					this.login.errors = response.data.errors
					this.login.loading = false
				})
				.catch(error => {
					console.log(error);
				});
		},
		signOut() {
			axios.post( '/wp-json/localmeet/v1/login/', {
				command: "signOut" 
			})
			.then( response => {
				window.location = "/"
				this.route = "login"
				this.wp_nonce = "";
			})
		},
	}
})
</script>
</body>
</html>