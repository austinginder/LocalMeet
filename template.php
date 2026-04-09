<?php
if ( ! function_exists( 'is_plugin_active' ) ) {
    function is_plugin_active( $plugin ) {
        return in_array( $plugin, (array) get_option( 'active_plugins', array() ) );
    }
}
?><!DOCTYPE html>
<html>
<head>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<script src="https://cdn.tailwindcss.com"></script>
	<script>
	tailwind.config = {
		darkMode: 'class',
		theme: {
			fontFamily: {
				sans: ['Inter', 'system-ui', 'sans-serif'],
			},
			fontSize: {
				'xs': ['0.8125rem', { lineHeight: '1.25rem' }],
				'sm': ['1rem', { lineHeight: '1.5rem' }],
			},
			extend: {
				colors: {
					primary: {
						50: '#eef2ff',
						100: '#e0e7ff',
						200: '#c7d2fe',
						300: '#a5b4fc',
						400: '#818cf8',
						500: '#6366f1',
						600: '#2849c5',
						700: '#2240ab',
						800: '#1e3691',
						900: '#1a2f77',
						950: '#111d4d',
					}
				}
			}
		}
	}
	</script>
	<script>
	if (localStorage.getItem('theme') === 'light') {
		document.documentElement.classList.remove('dark')
	} else {
		document.documentElement.classList.add('dark')
	}
	</script>
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
	<meta name="description" content="<?php echo localmeet_meta_description(); ?>" />
	<?php localmeet_meta_tags(); ?>
	<?php echo localmeet_header_content_extracted(); ?>
	<title><?php localmeet_title(); ?></title>
	<style>
		[x-cloak] { display: none !important; }
		.dark { color-scheme: dark; }
		.prose-content p { margin: 1em 0; }
		.prose-content ul, .prose-content ol { margin: 0.75em 0; padding-left: 1.5em; }
		.prose-content ul { list-style-type: disc; }
		.prose-content ol { list-style-type: decimal; }
		.prose-content li { margin: 0.25em 0; }
		.prose-content h1, .prose-content h2, .prose-content h3, .prose-content h4 { font-weight: 600; margin: 1em 0 0.5em; }
		.prose-content h1 { font-size: 1.5em; }
		.prose-content h2 { font-size: 1.25em; }
		.prose-content h3 { font-size: 1.125em; }
		.prose-content a { color: #2849c5; text-decoration: underline; }
		.dark .prose-content a { color: #818cf8; }
		.prose-content blockquote { border-left: 3px solid #d1d5db; padding-left: 1em; margin: 1em 0; color: #6b7280; }
		.dark .prose-content blockquote { border-color: #4b5563; color: #9ca3af; }
		.prose-content code { background: #f3f4f6; padding: 0.125em 0.375em; border-radius: 0.25em; font-size: 0.875em; }
		.dark .prose-content code { background: #374151; }
		.prose-content pre code { background: none; padding: 0; }
		.prose-content pre { background: #f3f4f6; padding: 1em; border-radius: 0.5em; overflow-x: auto; margin: 1em 0; }
		.dark .prose-content pre { background: #1f2937; }
	</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 antialiased">
<div id="ssr-content" class="max-w-xl mx-auto px-4 py-6"><?php localmeet_content(); ?></div>
<noscript><style>#ssr-content{display:block!important}</style></noscript>
<div id="app-loading" class="sticky top-0 z-30 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
	<div class="px-6 h-14 flex items-center">
		<div class="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
	</div>
</div>
<div x-data="localmeet()" x-cloak>
<?php echo file_get_contents( plugin_dir_path(__FILE__) . "template.html" ) ?>
</div>

<script>
<?php if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) { ?>
var wc_countries = <?php $countries = ( new WC_Countries )->get_allowed_countries(); $results = []; foreach ( $countries as $key => $county ) { $results[] = [ "text" => $county, "value" => $key ]; }; echo json_encode( $results ); ?>;
var wc_states = <?php echo json_encode( array_merge( WC()->countries->get_allowed_country_states(), WC()->countries->get_shipping_country_states() ) ); ?>;
<?php } else { ?>
var wc_countries = [];
var wc_states = [];
<?php } ?>

function localmeet() {
	return {
		countries: wc_countries,
		plugins_url: "<?php echo plugins_url(); ?>",
		drawer: false,
		logged_in: false,
		wp_nonce: "",
		attend_menu: false,
		attend_selection: "",
		event: {},
		event_loading: true,
		new_event: { show: false, time: "", end_time: "", date: "", name: "", location: "", location_name: "", location_address: "", group_id: "", description: "", capacity: "", recurrence_rule: "", image_id: null, image_url: null, image_picker: false, image_uploading: false, errors: [] },
		edit_event: { show: false, time: "", end_time: "", date: "", errors: [], image_picker: false, image_uploading: false, event: {} },
		my_images: [],
		notice: { show: false, subject: '', message: '', sending: false },
		announce: { show: false, loading: false, sending: false, preview_email: '', subscriber_count: 0, sent: 0, total: 0, errors: [], poll_timer: null },
		new_location: { show: false, name: "", address: "", notes: "", errors: [] },
		edit_location: { show: false, errors: [], location: {} },
		attend_event: { show: false, event_id: "", first_name: "", last_name: "", email: "", errors: [] },
		new_comment: "",
		edit_group: { show: false, errors: [], test_sending: false, group: {} },
		group: {},
		group_join_request: { show: false, errors: [], first_name: "", last_name: "", email: "" },
		group_nav: 1,
		groups: [],
		groups_total: 0,
		groups_page: 1,
		groups_loading: false,
		group_new: { errors: [], name: "", email: "", description: "" },
		group_apply: { address: { country: "US" }, type: "new" },
		group_search: "",
		event_search: "",
		past_events_page: 1,
		merge_users: { show: false, loading: false, errors: [], candidates: [], members: [], keep_user: null, merge_user: null },
		member_leave: {},
		rsvp_data: {},
		login: { show: false, user_login: "", user_password: "", errors: "", loading: false, lost_password: false, message: "" },
		organization: {},
		page: "",
		snackbar: { show: false, message: "" },
		states: wc_states,
		states_selected: [],
		user: <?php echo json_encode( ( new LocalMeet\App )->current_user() ); ?>,
		route: "",
		route_path: "",
		invite: { email: '', group_allowance: 1, errors: [] },
		invites: [],
		routes: {
			'': '',
			'/': '',
			'/profile': 'profile',
			'/sign-out': 'sign-out',
			'/start-group': 'start-group',
			'/find-group': 'find-group',
			'/admin/invites': 'admin-invites',
			'/account': 'account',
		},
		user_menu: false,
		editing_comment_id: null,
		darkMode: document.documentElement.classList.contains('dark'),

		get hasSidebar() {
			return this.route !== '' && this.route !== 'start-group' && this.route !== 'find-group' && this.route !== 'missing' && this.route !== 'profile' && this.route !== 'admin-invites'
		},
		get filteredGroups() {
			return [...this.groups].sort((a, b) => {
				const aScore = a.is_organizer ? 2 : (a.is_pinned ? 1 : 0)
				const bScore = b.is_organizer ? 2 : (b.is_pinned ? 1 : 0)
				return bScore - aScore
			})
		},

		init() {
			const loading = document.getElementById('app-loading')
			if (loading) loading.remove()
			const ssr = document.getElementById('ssr-content')
			if (ssr) ssr.remove()

			this.fetchGroups()
			this._searchTimeout = null
			this.$watch('group_search', (val) => {
				clearTimeout(this._searchTimeout)
				this._searchTimeout = setTimeout(() => this.searchGroups(), 300)
			})
			this._eventSearchTimeout = null
			this.$watch('event_search', () => {
				clearTimeout(this._eventSearchTimeout)
				this._eventSearchTimeout = setTimeout(() => this.searchEvents(), 300)
			})
			if (typeof wpApiSettings === 'undefined') {
				this.logged_in = false
			} else {
				this.wp_nonce = wpApiSettings.nonce
				this.logged_in = true
			}
			window.addEventListener('popstate', () => this.updateRoute(window.location.pathname))

			this.$watch('route', () => this.triggerRoute())
			this.$watch('route_path', () => this.triggerPath())
			this.$watch('snackbar.show', (val) => {
				if (val) setTimeout(() => this.snackbar.show = false, 3000)
			})

			this.updateRoute(window.location.pathname)
			this.triggerRoute()
		},

		refreshUser() {
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'currentUser' })
			})
			.then(r => r.json())
			.then(data => {
				if (data.nonce) this.wp_nonce = data.nonce
				if (data.can_create_group !== undefined) {
					this.user.can_create_group = data.can_create_group
				}
			})
			.catch(() => {})
		},

		toggleDarkMode() {
			this.darkMode = !this.darkMode
			document.documentElement.classList.toggle('dark', this.darkMode)
			localStorage.setItem('theme', this.darkMode ? 'dark' : 'light')
		},

		formatShortDate(dateStr) {
			if (!dateStr) return ''
			const d = new Date(dateStr.replace(' ', 'T'))
			return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
		},

		mapAddress(loc) {
			if (!loc) return ''
			try {
				const parsed = JSON.parse(loc)
				if (typeof parsed === 'object' && parsed !== null) {
					if (parsed.address) return parsed.address
					return parsed.name || ''
				}
				return loc
			} catch (e) {
				return loc
			}
		},
		formatLocation(loc) {
			if (!loc) return ''
			try {
				const parsed = JSON.parse(loc)
				if (typeof parsed === 'object' && parsed !== null) {
					const parts = [parsed.name, parsed.address].filter(Boolean)
					return parts.join(' - ')
				}
				return loc
			} catch (e) {
				return loc
			}
		},
		prettyTimestamp(date) {
			return new Date(date).toLocaleTimeString("en-us", { weekday: "short", year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })
		},
		prettyDayTimestamp(date) {
			return new Date(date).toLocaleDateString("en-us", { year: "numeric", month: "long", day: "numeric" })
		},
		apiHeaders() {
			const h = { 'Content-Type': 'application/json' }
			if (this.wp_nonce) h['X-WP-Nonce'] = this.wp_nonce
			return h
		},
		async refreshNonce() {
			const r = await fetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ command: 'currentUser' })
			})
			const data = await r.json()
			if (data.nonce) {
				this.wp_nonce = data.nonce
			}
		},
		async apiFetch(url, options = {}) {
			if (!options.headers) options.headers = this.apiHeaders()
			let r = await fetch(url, options)
			if (r.status === 403 && this.logged_in) {
				await this.refreshNonce()
				options.headers = this.apiHeaders()
				r = await fetch(url, options)
			}
			return r
		},

		groupLink(page) {
			const org = this.organization.slug || 'group'
			return `/${org}/${this.group.slug}/${page}`
		},
		groupHomeLink(group) {
			let organization = 'group'
			if (group.organization_id !== '0' && this.organization.slug) {
				organization = this.organization.slug
			}
			return `/${organization}/${group.slug}`
		},
		updateRoute(href) {
			const page_depth = href.match(/\//g).length
			if (href.slice(-1) === '/') href = href.slice(0, -1)
			// Check full path against known routes first
			if (typeof this.routes[href] !== 'undefined') {
				this.route_path = ''
				this.route = this.routes[href]
				return
			}
			let new_route_path = ''
			let base_href = href
			if (href !== '' && href.match(/\//g).length > 1) {
				new_route_path = href.split('/').slice(2).join('/')
				base_href = href.split('/').slice(0, 2).join('/')
			}
			let new_route = this.routes[base_href]
			if (typeof new_route === 'undefined') {
				const event = window.location.pathname.split('/').slice(3, 4).join('/')
				if (page_depth === 1) new_route = 'organization'
				const sub_action = window.location.pathname.split('/').slice(4, 5).join('/')
				if (page_depth === 2 || event === 'leave' || event === 'members' || event === 'events' || event === 'locations') new_route = 'group'
				if (sub_action === 'rsvp') new_route = 'group'
				if (page_depth === 3 && event !== 'leave' && event !== 'members' && event !== 'events' && event !== 'locations' && !sub_action) {
					this.event_loading = true
					new_route = 'event'
				}
			}
			this.route_path = new_route_path
			this.route = new_route
		},
		triggerRoute() {
			const page_depth = window.location.pathname.match(/\//g).length
			const organization = window.location.pathname.split('/').slice(1, 2).join('/')
			const group = window.location.pathname.split('/').slice(2, 3).join('/')
			const event = window.location.pathname.split('/').slice(3, 4).join('/')

			if (page_depth === 3) {
				this.page = window.location.pathname.split('/').slice(0, 2).join('/')
			}
			if (this.route === '') {
				this.group = {}
				this.organization = {}
				this.fetchGroups()
			}
			if (this.route === 'profile') {
				this.group = {}
				this.organization = {}
			}
			if (this.route === 'sign-out') this.signOut()
			if (this.route === 'start-group') this.group = {}
			if (this.route === 'event') {
				this.fetchEvent()
				if (!this.group.slug) this.fetchGroup()
				const urlParams = new URLSearchParams(window.location.search)
				if (urlParams.get('rsvp')) {
					this.snackbar.message = 'You are confirmed.'
					this.snackbar.show = true
				}
			}
			if (this.route === 'find-group') {
				this.fetchGroups()
				this.group = {}
				this.organization = {}
			}
			if (this.route === 'group') {
				if (organization === 'group') this.organization = {}
				if (group === 'start-group') {
					this.populateStates()
					this.group = {}
				}
				if (group !== 'start-group') this.fetchGroup()
				if (organization !== 'group') this.fetchOrganization()
				const urlParams = new URLSearchParams(window.location.search)
				if (urlParams.get('joined')) {
					this.snackbar.message = 'Welcome to the group!'
					this.snackbar.show = true
				}
				if (urlParams.get('muted') === 'confirmed') {
					this.snackbar.message = 'Unsubscribed. You will no longer receive emails from this group.'
					this.snackbar.show = true
				}
			}
			if (this.route === 'organization') this.fetchOrganization()
			if (this.route === 'admin-invites') this.fetchInvites()
		},
		triggerPath() {
			if (this.route_path === 'start-group') this.group = {}
		},
		goToPath(href) {
			const prevRoute = this.route
			window.history.pushState({}, '', href)
			this.updateRoute(href)
			// If route didn't change (e.g. group->group or event->event),
			// the $watch won't fire, so trigger manually
			if (this.route === prevRoute) this.triggerRoute()
		},

		createGroup() {
			this.group_new.errors = []
			this.apiFetch('/wp-json/localmeet/v1/groups/create', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ request: this.group_new })
			})
			.then(r => r.json())
			.then(data => {
				if (typeof data.errors === 'undefined' || data.errors.length === 0) {
					this.group_new = { errors: [], name: '', email: '', description: '' }
					if (data.redirect) {
						this.goToPath(data.redirect)
					} else {
						this.route = ''
						window.history.pushState({}, 'LocalMeet', '/')
					}
					this.refreshUser()
					return
				}
				this.group_new.errors = data.errors
			})
			.catch(err => console.log(err))
		},
		fetchGroups() {
			this.groups_page = 1
			this.groups_loading = true
			this.apiFetch(`/wp-json/localmeet/v1/groups?per_page=20&page=1`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.groups = data.groups
				this.groups_total = data.total
				this.groups_loading = false
			})
		},
		loadMoreGroups() {
			this.groups_page++
			this.groups_loading = true
			this.apiFetch(`/wp-json/localmeet/v1/groups?per_page=20&page=${this.groups_page}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.groups = this.groups.concat(data.groups)
				this.groups_loading = false
			})
		},
		searchGroups() {
			if (!this.group_search || this.group_search.length < 2) {
				this.fetchGroups()
				return
			}
			this.groups_loading = true
			this.apiFetch(`/wp-json/localmeet/v1/groups/search?q=${encodeURIComponent(this.group_search)}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.groups = data.groups
				this.groups_total = data.total
				this.groups_loading = false
			})
		},
		fetchGroup(events_page = 1) {
			this.past_events_page = 1
			this.event_search = ''
			const org = window.location.pathname.split('/').slice(1)[0]
			const group = window.location.pathname.split('/').slice(2, 3).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/group/${group}?organization=${org}&events_per_page=20&events_page=${events_page}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				const action = window.location.pathname.split('/').slice(3, 4).join('/')
				const sub_action_group = window.location.pathname.split('/').slice(4, 5).join('/')
				if (action === 'leave') {
					data.show = 'leave'
					this.fetchLeave()
				} else if (sub_action_group === 'rsvp') {
					data.show = 'rsvp'
					this.fetchRsvp()
				} else if (action === 'members') {
					data.show = 'members'
				} else if (action === 'events') {
					data.show = 'list'
				} else if (action === 'locations') {
					data.show = 'locations'
				} else if (!data.show) {
					data.show = 'list'
				}
				this.group = data
			})
		},
		get pastTotalPages() {
			return Math.ceil((this.group.past_total || 0) / 20)
		},
		goToPastPage(page) {
			if (page < 1 || page > this.pastTotalPages) return
			this.past_events_page = page
			const org = window.location.pathname.split('/').slice(1)[0]
			const group_slug = window.location.pathname.split('/').slice(2, 3).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/group/${group_slug}?organization=${org}&events_per_page=20&events_page=${this.past_events_page}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.group.past = data.past
				this.group.past_total = data.past_total
			})
		},
		searchEvents() {
			if (!this.event_search || this.event_search.length < 2) {
				this.fetchGroup()
				return
			}
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/events/search?q=${encodeURIComponent(this.event_search)}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.group.upcoming = data.upcoming || []
				this.group.upcoming_total = data.upcoming.length
				this.group.past = data.past || []
				this.group.past_total = data.past.length
			})
		},
		fetchLeave() {
			const urlParams = new URLSearchParams(window.location.search)
			const token = urlParams.get('token')
			this.apiFetch(`/wp-json/localmeet/v1/member/get/${token}`)
			.then(r => r.json())
			.then(data => this.member_leave = data)
			.catch(() => this.route = 'missing')
		},
		fetchRsvp() {
			const urlParams = new URLSearchParams(window.location.search)
			const token = urlParams.get('token')
			const event_slug = window.location.pathname.split('/').slice(3, 4).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/event/${event_slug}/rsvp-info/${token}`)
			.then(r => r.json())
			.then(data => {
				if (data.code) { this.route = 'missing'; return }
				this.rsvp_data = data
			})
			.catch(() => this.route = 'missing')
		},
		confirmRsvp() {
			const urlParams = new URLSearchParams(window.location.search)
			const token = urlParams.get('token')
			const event_slug = window.location.pathname.split('/').slice(3, 4).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/event/${event_slug}/rsvp-confirm/${token}`, {
				method: 'POST'
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.rsvp_data.is_going = true
				this.snackbar.message = "You're going!"
				this.snackbar.show = true
			})
		},
		editGroup() {
			this.edit_group = { show: true, errors: [], test_sending: false, group: JSON.parse(JSON.stringify(this.group)) }
		},
		joinGroup() {
			if (this.user.username) {
				this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/join`, { headers: this.apiHeaders() })
				.then(() => {
					this.fetchGroup()
					this.snackbar.message = `Joined group '${this.group.name}'`
					this.snackbar.show = true
				})
				return
			}
			this.group_join_request.show = true
		},
		joinGroupRequest() {
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/join`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ request: this.group_join_request })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors.length > 0) {
					this.group_join_request.errors = data.errors
					return
				}
				this.group_join_request = { show: false, errors: [], first_name: '', last_name: '', email: '' }
				this.snackbar.message = `Check your email to complete joining group '${this.group.name}'`
				this.snackbar.show = true
			})
		},
		leaveGroup() {
			if (this.user.username) {
				this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/leave`, { headers: this.apiHeaders() })
				.then(() => {
					this.fetchGroup()
					this.snackbar.message = `Left group '${this.group.name}'`
					this.snackbar.show = true
				})
			}
		},
		leaveGroupConfirm() {
			const urlParams = new URLSearchParams(window.location.search)
			const token = urlParams.get('token')
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/leave/${token}`)
			.then(() => {
				this.fetchGroup()
				this.goToPath(this.groupHomeLink(this.group))
				this.snackbar.message = `Left group '${this.group.name}'`
				this.snackbar.show = true
			})
		},
		sendTestEmail() {
			this.edit_group.test_sending = true
			fetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/test-email`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(r => r.json())
			.then(data => {
				this.edit_group.test_sending = false
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.snackbar.message = data.message
				this.snackbar.show = true
			})
		},
		updateGroup() {
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.edit_group.group.group_id}/update`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ edit_group: this.edit_group })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.edit_group.errors = data.errors
					return
				}
				this.edit_group = { show: false, errors: [], test_sending: false, group: {} }
				if (data.slug && data.slug !== this.group.slug) {
					this.goToPath(`/group/${data.slug}`)
				} else {
					this.fetchGroup()
				}
			})
		},
		deleteGroup() {
			if (!confirm('Delete group? Warning all past events will be deleted.')) return
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/delete`, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(() => {
				this.fetchGroups()
				this.edit_group = { show: false, errors: [], test_sending: false, group: {} }
				this.snackbar.message = 'Group has been deleted.'
				this.snackbar.show = true
				this.goToPath('/')
				this.refreshUser()
			})
		},

		addLocation() {
			this.new_location.errors = []
			if (!this.new_location.name.trim()) this.new_location.errors.push('Name is required.')
			if (this.new_location.errors.length > 0) return
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/locations/create`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ location: this.new_location })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) { this.new_location.errors = data.errors; return }
				this.fetchGroup()
				this.new_location = { show: false, name: '', address: '', notes: '', errors: [] }
			})
		},
		editLocation(location) {
			this.edit_location = { show: true, errors: [], location: JSON.parse(JSON.stringify(location)) }
		},
		updateLocation() {
			this.edit_location.errors = []
			if (!this.edit_location.location.name || !this.edit_location.location.name.trim()) this.edit_location.errors.push('Name is required.')
			if (this.edit_location.errors.length > 0) return
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/location/${this.edit_location.location.location_id}/update`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ location: this.edit_location.location })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) { this.edit_location.errors = data.errors; return }
				this.fetchGroup()
				this.edit_location = { show: false, errors: [], location: {} }
			})
		},
		deleteLocation() {
			if (!confirm('Delete this location?')) return
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/location/${this.edit_location.location.location_id}/delete`, {
				method: 'DELETE',
				headers: this.apiHeaders()
			})
			.then(() => {
				this.fetchGroup()
				this.edit_location = { show: false, errors: [], location: {} }
				this.snackbar.message = 'Location has been deleted.'
				this.snackbar.show = true
			})
		},

		fetchEvent() {
			const organization = window.location.pathname.split('/').slice(1, 2).join('/')
			const group = window.location.pathname.split('/').slice(2, 3).join('/')
			const event = window.location.pathname.split('/').slice(3, 4).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/event/${event}?organization=${organization}&group=${group}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.event = data
				this.event_loading = false
				this.attend_selection = (data.attendees || []).some(a => a.is_me) ? 'going' : (data.attendees_not || []).some(a => a.is_me) ? 'not-going' : ''
			})
			.catch(() => this.route = 'missing')
		},
		loadMoreComments() {
			const organization = window.location.pathname.split('/').slice(1, 2).join('/')
			const group = window.location.pathname.split('/').slice(2, 3).join('/')
			const event_slug = window.location.pathname.split('/').slice(3, 4).join('/')
			const page = Math.floor(this.event.comments.length / 50) + 1
			this.apiFetch(`/wp-json/localmeet/v1/event/${event_slug}?organization=${organization}&group=${group}&comments_page=${page}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.event.comments = this.event.comments.concat(data.comments)
				this.event.comments_total = data.comments_total
			})
		},
		addEvent() {
			this.new_event.errors = []
			if (!this.new_event.name.trim()) this.new_event.errors.push('Name is required.')
			if (!this.new_event.date) this.new_event.errors.push('Date is required.')
			if (!this.new_event.time) this.new_event.errors.push('Time is required.')
			if (this.new_event.date && this.new_event.time) {
				const eventDate = new Date(`${this.new_event.date}T${this.new_event.time}`)
				if (eventDate < new Date()) this.new_event.errors.push('Event date must be in the future.')
			}
			if (this.new_event.errors.length > 0) return
			this.new_event.group_id = this.group.group_id
			this.apiFetch('/wp-json/localmeet/v1/events/create', {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ new_event: this.new_event })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) { this.new_event.errors = data.errors; return }
				this.fetchGroup()
				this.new_event = { show: false, time: '', end_time: '', date: '', name: '', location: '', location_name: '', location_address: '', group_id: '', description: '', capacity: '', recurrence_rule: '', image_id: null, image_url: null, image_picker: false, image_uploading: false, errors: [] }
			})
		},
		announceEvent() {
			this.announce = { show: true, loading: true, sending: false, preview_email: '', subscriber_count: 0, sent: 0, total: 0, errors: [], poll_timer: null }
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/announce-info`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.announce.loading = false
				if (data.errors) { this.announce.errors = data.errors; return }
				this.announce.subscriber_count = data.subscriber_count
				if (data.announced_at) this.event.announced_at = data.announced_at
				if (data.sending) {
					this.announce.sending = true
					this.announce.sent = data.sent
					this.announce.total = data.total
					this.pollAnnounceStatus()
				}
			})
		},
		sendAnnouncement() {
			this.announce.sending = true
			this.announce.sent = 0
			this.announce.total = this.announce.subscriber_count
			this.announce.errors = []
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/announce`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.announce.sending = false
					this.announce.errors = data.errors
					return
				}
				this.pollAnnounceStatus()
			})
		},
		pollAnnounceStatus() {
			this.announce.poll_timer = setInterval(() => {
				this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/announce-status`, { headers: this.apiHeaders() })
				.then(r => r.json())
				.then(data => {
					if (data.status === 'sending') {
						this.announce.sent = data.sent
						this.announce.total = data.total
					}
					if (data.status === 'complete') {
						clearInterval(this.announce.poll_timer)
						this.announce.poll_timer = null
						this.announce.sending = false
						this.announce.sent = data.total
						this.event.announced_at = data.announced_at
						this.snackbar.message = 'Announcement sent to all subscribers.'
						this.snackbar.show = true
					}
					if (data.status === 'idle' && this.announce.sending) {
						clearInterval(this.announce.poll_timer)
						this.announce.poll_timer = null
						this.announce.sending = false
					}
				})
			}, 2000)
		},
		sendAnnouncementPreview(email) {
			this.announce.errors = []
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/announce-preview`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ email: email || '' })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.snackbar.message = data.message
				this.snackbar.show = true
			})
		},
		editEvent() {
			if (!this.event || !this.event.event_at) return
			this.edit_event = { show: true, time: '', end_time: '', date: '', errors: [], image_picker: false, image_uploading: false, event: JSON.parse(JSON.stringify(this.event)) }
			this.edit_event.date = this.edit_event.event.event_at.substr(0, 10)
			this.edit_event.time = this.edit_event.event.event_at.substr(11, 8)
			this.edit_event.end_time = this.edit_event.event.event_end_at ? this.edit_event.event.event_end_at.substr(11, 8) : ''
			this.edit_event.event.location_name = this.event.location_name || ''
			this.edit_event.event.location_address = this.event.location_address || ''
		},
		updateEvent() {
			this.edit_event.errors = []
			if (!this.edit_event.event.name || !this.edit_event.event.name.trim()) this.edit_event.errors.push('Name is required.')
			if (!this.edit_event.date) this.edit_event.errors.push('Date is required.')
			if (!this.edit_event.time) this.edit_event.errors.push('Time is required.')
			if (this.edit_event.errors.length > 0) return
			const event_id = this.edit_event.event.event_id
			this.edit_event.event.event_at = `${this.edit_event.date} ${this.edit_event.time}`
			this.edit_event.event.event_end_at = this.edit_event.end_time ? `${this.edit_event.date} ${this.edit_event.end_time}` : null
			this.apiFetch(`/wp-json/localmeet/v1/event/${event_id}/update`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ edit_event: this.edit_event })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.edit_event.errors = data.errors
					return
				}
				this.edit_event = { show: false, time: '', date: '', errors: [], event: {} }
				if (data.slug) {
					const org = window.location.pathname.split('/').slice(1)[0]
					const groupSlug = window.location.pathname.split('/').slice(2, 3)[0]
					this.goToPath(`/${org}/${groupSlug}/${data.slug}`)
				}
				this.fetchEvent()
				// Show prefilled notice if time/location changed
				if (data.notice_subject) {
					this.notice = { show: true, subject: data.notice_subject, message: data.notice_message, sending: false }
				}
			})
		},
		uploadImage(event, target) {
			const file = event.dataTransfer?.files[0] || event.target?.files[0]
			if (!file) return
			if (!file.type.startsWith('image/')) {
				this.snackbar.message = 'Please select an image file.'
				this.snackbar.show = true
				return
			}
			const formData = new FormData()
			formData.append('file', file)
			if (target === 'new_event') this.new_event.image_uploading = true
			else this.edit_event.image_uploading = true
			this.apiFetch('/wp-json/localmeet/v1/media/upload', {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce },
				body: formData
			})
			.then(r => r.json())
			.then(data => {
				if (target === 'new_event') {
					this.new_event.image_uploading = false
					if (data.errors) { this.snackbar.message = data.errors[0]; this.snackbar.show = true; return }
					this.new_event.image_id = data.id
					this.new_event.image_url = data.url
					this.new_event.image_picker = false
				} else {
					this.edit_event.image_uploading = false
					if (data.errors) { this.snackbar.message = data.errors[0]; this.snackbar.show = true; return }
					this.edit_event.event.image_id = data.id
					this.edit_event.event.image_url = data.url
					this.edit_event.image_picker = false
				}
				this.my_images.unshift({ id: data.id, url: data.url, thumbnail: data.thumbnail })
			})
			.catch(() => {
				if (target === 'new_event') this.new_event.image_uploading = false
				else this.edit_event.image_uploading = false
			})
			if (event.target?.value) event.target.value = ''
		},
		fetchMyImages() {
			this.apiFetch('/wp-json/localmeet/v1/media/mine', { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => this.my_images = data)
		},
		cancelEvent() {
			if (!confirm('Cancel this event? Members will be prompted to be notified.')) return
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/cancel`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.edit_event = { show: false, time: '', date: '', errors: [], event: {} }
				this.fetchEvent()
				this.fetchGroup()
				// Show prefilled notice
				if (data.notice_subject) {
					this.notice = { show: true, subject: data.notice_subject, message: data.notice_message, sending: false }
				}
			})
		},
		sendNotice() {
			if (!this.notice.subject || !this.notice.message) return
			this.notice.sending = true
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/notice`, {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ subject: this.notice.subject, message: this.notice.message })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.notice.sending = false
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.notice = { show: false, subject: '', message: '', sending: false }
				this.snackbar.message = `Sending notice to ${data.total} members...`
				this.snackbar.show = true
			})
		},
		deleteEvent() {
			if (!confirm('Delete event?')) return
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/delete`, {
				method: 'DELETE',
				headers: { 'X-WP-Nonce': this.wp_nonce }
			})
			.then(() => {
				this.edit_event = { show: false, time: '', date: '', errors: [], event: {} }
				this.snackbar.message = 'Event has been deleted.'
				this.snackbar.show = true
				this.goToPath(this.groupHomeLink(this.group))
				this.fetchGroup()
			})
		},

		attendEventRequest() {
			this.attend_event.event_id = this.event.event_id
			this.apiFetch('/wp-json/localmeet/v1/attendee/create', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ request: this.attend_event })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.attend_event.errors = data.errors
					return
				}
				this.snackbar.message = 'Please check your email to confirm.'
				this.snackbar.show = true
				this.fetchEvent()
				this.attend_event = { show: false, event_id: '', first_name: '', last_name: '', email: '', errors: [] }
			})
		},
		attendEvent() {
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/attend`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ selection: this.attend_selection })
			})
			.then(() => {
				this.attend_menu = false
				this.fetchEvent()
			})
		},

		addComment() {
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/comment/new`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ comment: this.new_comment })
			})
			.then(r => r.json())
			.then(() => {
				this.new_comment = ''
				this.fetchEvent()
			})
		},
		updateComment(comment) {
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/comment/${comment.comment_id}/update`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ comment: comment.details_raw })
			})
			.then(r => r.json())
			.then(() => {
				this.editing_comment_id = null
				this.fetchEvent()
			})
		},
		deleteComment(comment_id) {
			if (!confirm('Delete comment?')) return
			const comment = this.event.comments.filter(c => c.comment_id === comment_id)
			if (comment.length !== 1) return
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/comment/delete`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ comment_id })
			})
			.then(r => r.json())
			.then(() => this.fetchEvent())
		},

		transferOrganizer(member) {
			if (!confirm(`Transfer organizer role to ${member.first_name} ${member.last_name}? You will become a regular member.`)) return
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/transfer`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ user_id: member.user_id })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				this.fetchGroup()
				this.snackbar.message = `Organizer role transferred to ${member.first_name} ${member.last_name}.`
				this.snackbar.show = true
			})
		},
		openMergeUsers() {
			this.merge_users = { show: true, loading: true, errors: [], candidates: [], members: [], keep_user: null, merge_user: null }
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/merge-users/candidates`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => {
				this.merge_users.loading = false
				if (data.errors) { this.merge_users.errors = data.errors; return }
				this.merge_users.candidates = data.candidates
				this.merge_users.members = data.members
			})
		},
		selectMergePair(keep, merge) {
			this.merge_users.keep_user = keep.user_id
			this.merge_users.merge_user = merge.user_id
		},
		executeMerge() {
			const keep = this.merge_users.members.find(m => m.user_id == this.merge_users.keep_user)
			const merge = this.merge_users.members.find(m => m.user_id == this.merge_users.merge_user)
			if (!keep || !merge) { this.merge_users.errors = ['Select both users.']; return }
			if (!confirm(`Merge "${merge.display_name} (${merge.email})" into "${keep.display_name} (${keep.email})"?\n\nThe merged account will be permanently deleted.`)) return
			this.merge_users.loading = true
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/merge-users`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ keep_user_id: keep.user_id, merge_user_id: merge.user_id })
			})
			.then(r => r.json())
			.then(data => {
				this.merge_users.loading = false
				if (data.errors) { this.merge_users.errors = data.errors; return }
				this.merge_users.show = false
				this.snackbar.message = data.message
				this.snackbar.show = true
				this.fetchGroup()
			})
		},
		toggleMemberRole(member) {
			const newRole = member.role === 'manager' ? 'member' : 'manager'
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/member/${member.member_id}/role`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ role: newRole })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = data.errors[0]
					this.snackbar.show = true
					return
				}
				member.role = data.role
				this.snackbar.message = data.role === 'manager' ? 'Promoted to manager.' : 'Removed as manager.'
				this.snackbar.show = true
			})
		},
		toggleNotifications() {
			this.apiFetch(`/wp-json/localmeet/v1/group/${this.group.group_id}/notifications`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ enabled: !this.group.email_notifications })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) return
				this.group.email_notifications = data.email_notifications
				this.snackbar.message = data.email_notifications ? 'Email notifications enabled.' : 'Unsubscribed from email notifications.'
				this.snackbar.show = true
			})
		},
		moderateComment(comment_id, action) {
			this.apiFetch(`/wp-json/localmeet/v1/event/${this.event.event_id}/comment/${comment_id}/moderate`, {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ action })
			})
			.then(r => r.json())
			.then(() => this.fetchEvent())
		},

		fetchOrganization() {
			const organization = window.location.pathname.split('/').slice(1, 2).join('/')
			this.apiFetch(`/wp-json/localmeet/v1/organization/${organization}`, { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => this.organization = data)
			.catch(() => this.route = 'missing')
		},

		createInvite() {
			this.invite.errors = []
			this.apiFetch('/wp-json/localmeet/v1/invite/create', {
				method: 'POST',
				headers: { 'X-WP-Nonce': this.wp_nonce, 'Content-Type': 'application/json' },
				body: JSON.stringify({ invite: this.invite })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.invite.errors = data.errors
					return
				}
				this.snackbar.message = data.message
				this.snackbar.show = true
				this.invite = { email: '', group_allowance: 1, errors: [] }
				this.fetchInvites()
			})
		},
		fetchInvites() {
			this.apiFetch('/wp-json/localmeet/v1/invites', { headers: this.apiHeaders() })
			.then(r => r.json())
			.then(data => this.invites = data)
		},

		populateStates() {
			const states_selected = []
			const select = this.states[this.group_apply.address.country]
			if (typeof select !== 'object') {
				this.states_selected = []
				return
			}
			Object.entries(select).forEach(([key, value]) => {
				states_selected.push({ text: value, value: key })
			})
			this.states_selected = states_selected
		},

		setPassword() {
			this.user.errors = []
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'setPassword', password: this.user.new_password })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.user.errors = data.errors
					return
				}
				this.user.password_not_set = false
				this.user.new_password = ''
				this.snackbar.message = 'Password has been set.'
				this.snackbar.show = true
			})
		},
		updateAccount() {
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'updateAccount', user: this.user })
			})
			.then(r => r.json())
			.then(data => {
				if (data.errors) {
					this.snackbar.message = 'Failed to update your account.'
					this.snackbar.show = true
					this.user.errors = data.errors
					return
				}
				this.snackbar.message = 'Account updated.'
				this.snackbar.show = true
				this.user.name = data.user.name
				this.user.errors = []
				this.user.new_password = ''
			})
			.catch(err => console.log(err))
		},
		signIn() {
			this.login.loading = true
			const form = this.$refs.loginForm
			if (form && !form.checkValidity()) {
				form.reportValidity()
				this.login.loading = false
				return
			}
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'signIn', login: this.login })
			})
			.then(r => r.json())
			.then(data => {
				if (typeof data.errors === 'undefined') {
					window.location = window.location
					return
				}
				this.login.errors = data.errors
				this.login.loading = false
			})
			.catch(err => console.log(err))
		},
		signOut() {
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'signOut' })
			})
			.then(() => {
				window.location = '/'
				this.route = 'login'
				this.wp_nonce = ''
			})
		},
		resetPassword() {
			this.login.loading = true
			const form = this.$refs.resetForm
			if (form && !form.checkValidity()) {
				form.reportValidity()
				this.login.loading = false
				return
			}
			this.apiFetch('/wp-json/localmeet/v1/login/', {
				method: 'POST',
				headers: this.apiHeaders(),
				body: JSON.stringify({ command: 'reset', login: this.login })
			})
			.then(r => r.json())
			.then(() => {
				this.login.message = "A password reset email is on it's way."
				this.login.loading = false
			})
			.catch(err => console.log(err))
		},
	}
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
