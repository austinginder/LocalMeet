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
<div id="app" v-cloak>
<v-app>
	<v-main>
	<v-app-bar app class="mb-5" style="left:0px" color="#fff">
		<v-app-bar-nav-icon @click.stop="drawer = !drawer" class="d-md-none d-lg-none d-xl-none" v-show="route != '' && route != 'start-group' && route != 'find-group'"></v-app-bar-nav-icon>
		<a href="/" @click.prevent="goToPath( '/')"><v-img src="<?php echo plugins_url(); ?>/localmeet/img/LocalMeet-logo.png" style="max-width: 200px;"></v-img></a>
		<v-spacer></v-spacer>
		Self-starting local meetups
	</v-app-bar>
	<v-navigation-drawer v-model="drawer" mobile-breakpoint="960" app clipped v-if="route != '' && route != 'start-group' && route != 'find-group'">
	<div v-if="typeof organization.name == 'undefined' && group.name">
	<v-card-title style="word-break: break-word;">{{ group.name }}</v-card-title>
	<v-card-text>{{ group.description }}</v-card-text>
    <v-list>
	<v-list-item :href="groupHomeLink( group )" @click.prevent="goToPath( groupHomeLink( group ) )">
    <v-list-item-content>
        <v-list-item-title>{{ group.past.length + group.upcoming.length  }}</v-list-item-title>
        <v-list-item-subtitle>Events</v-list-item-subtitle>
    </v-list-item-content>
    </v-list-item>
	<v-list-item v-show="user.role == 'administrator'">
    <v-list-item-content>
        <v-list-item-title></v-list-item-title>
        <v-list-item-subtitle><v-btn block depressed>Edit Group <v-icon class="ml-1">mdi-pencil-box</v-icon></v-btn></v-list-item-subtitle>
    </v-list-item-content>
    </v-list-item>
	
	</div>
    </v-list>
	<template v-slot:append>
	<v-list>
	<v-list-item v-show="! user.username">
		<v-list-item-content>
			<v-dialog max-width="600">
			<template v-slot:activator="{ on, attrs }">
				<v-btn v-bind="attrs" v-on="on">Sign In</v-btn>
			</template>
			<template v-slot:default="dialog">
			<v-card>
				<v-toolbar color="primary" dark>
					<v-toolbar-title>Sign In</v-toolbar-title>
					<v-spacer></v-spacer>
					<v-toolbar-items>
					<v-btn icon @click="dialog.value = false"><v-icon>mdi-close</v-icon></v-btn>
					</v-toolbar-items>
				</v-toolbar>
				<v-card-text class="mt-5">
				<v-form v-if="login.lost_password" ref="reset" @keyup.native.enter="resetPassword()">
					<v-row no-gutters>
						<v-col cols="12" class="py-0">
							<v-text-field label="Username or Email" :value="login.user_login" @change.native="login.user_login = $event.target.value" required :disabled="login.loading" :rules="[v => !!v || 'Username is required']"></v-text-field>
						</v-col>
						<v-col cols="12" class="py-0">
							<v-alert text type="success" v-show="login.message">{{ login.message }}</v-alert>
						</v-col>
						<v-col cols="12" class="py-0">
							<v-progress-linear indeterminate rounded height="6" class="mb-3" v-show="login.loading"></v-progress-linear>
							<v-btn color="primary" @click="resetPassword()" :disabled="login.loading">Reset Password</v-btn>
						</v-col>
					</v-row>
					</v-form>
					<v-form lazy-validation ref="login" @keyup.native.enter="signIn()" v-else>
					<v-row no-gutters>
						<v-col cols="12">
							<v-text-field label="Username or Email" :value="login.user_login" @change.native="login.user_login = $event.target.value" required :disabled="login.loading" :rules="[v => !!v || 'Username is required']"></v-text-field>
						</v-col>
						<v-col cols="12">
							<v-text-field label="Password" :value="login.user_password" @change.native="login.user_password = $event.target.value" required :disabled="login.loading" type="password" :rules="[v => !!v || 'Password is required']"></v-text-field>
						</v-col>
						<v-col cols="12">
							<v-alert text type="error" v-show="login.errors">{{ login.errors }}</v-alert>
						</v-col>
						<v-col cols="12">
							<v-progress-linear indeterminate rounded height="6" class="mb-3" v-show="login.loading"></v-progress-linear>
							<v-btn color="primary" @click="signIn()" :disabled="login.loading">Login</v-btn>
						</v-col>
					</v-row>
					</v-form>
				</v-card-text>
			</v-card>
			</template>
		</v-dialog>
		</v-list-item-content>
	</v-list-item>
	</v-list>
	  <v-menu offset-y top>
      <template v-slot:activator="{ on }">
		<v-list>
		<v-list-item link v-on="on" v-show="user.username">
			<v-list-item-avatar tile>
				<v-img :src="user.avatar"></v-img>
			</v-list-item-avatar>
			<v-list-item-content>
				<v-list-item-title>{{ user.name }}</v-list-item-title>
			</v-list-item-content>
			<v-list-item-icon>
				<v-icon>mdi-chevron-up</v-icon>
			</v-list-item-icon>
		</v-list-item>
		</v-list>
      </template>
      <v-list dense>
	  	<v-list-item link href="/profile" @click.prevent="goToPath( '/profile' )">
          <v-list-item-icon>
            <v-icon>mdi-account-box</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Profile</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
		<!--<v-list-item link v-if="footer.switch_to_link" :href="footer.switch_to_link">
          <v-list-item-icon>
            <v-icon>mdi-logout</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>{{ footer.switch_to_text }}</v-list-item-title>
          </v-list-item-content>
        </v-list-item>-->
	  	<v-list-item link href="/sign-out" @click.prevent="goToPath( '/sign-out' )">
          <v-list-item-icon>
            <v-icon>mdi-logout</v-icon>
          </v-list-item-icon>
          <v-list-item-content>
            <v-list-item-title>Log Out</v-list-item-title>
          </v-list-item-content>
        </v-list-item>
      </v-list>
    </v-menu>
      </template>
</v-navigation-drawer>

	<v-card flat v-if="route == ''" class="text-center">
	
	<div class="mt-10 mb-5"></div>
	<v-row class="text-center" style="max-width:600px;margin:auto">
		<v-col>
			<v-btn depressed x-large href="/start-group" @click.prevent="goToPath( '/start-group' )" class="mx-auto mt-7">Start a new group</v-btn>
		</v-col>
		<v-col>
			<v-btn depressed x-large href="/find-group" @click.prevent="goToPath( '/find-group' )" class="mx-auto mt-7">Find a group</v-btn>
		</v-col>
	</v-row>
		<v-alert color="primary" text type="info" style="max-width:600px;margin:auto" class="mt-10">LocalMeet is an <a href="https://github.com/austinginder/LocalMeet" target="_new">open source</a> meetup tool powered by WordPress.</v-alert>
	</v-card>
	<v-card v-if="route == 'group'" flat>
		<div v-show="group.name">
		<v-card class="my-5" style="max-width: 750px; margin: auto">
		<v-toolbar color="primary" dark flat>
			<v-toolbar-title>Events</v-toolbar-title>
			<v-spacer></v-spacer>
			<v-toolbar-items v-show="user.role == 'administrator' || group.owner">
				<v-dialog max-width="600" v-model="new_event.show" persistent>
					<template v-slot:activator="{ on, attrs }">
						<v-btn icon v-bind="attrs" v-on="on"><v-icon large>mdi-plus-box</v-icon></v-btn>
					</template>
					<template v-slot:default="dialog">
					<v-card>
						<v-toolbar color="primary" dark>
							<v-toolbar-title>New Event</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-toolbar-items>
								<v-btn icon @click="dialog.value = false"><v-icon large>mdi-close-box</v-icon></v-btn>
							<v-toolbar-items>
						</v-toolbar>
						<v-card-text>
							<v-row no-gutters>
								<v-col class="mt-3">
									<v-text-field label="Name" v-model="new_event.name" no-details></v-text-field>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
								<v-menu
									v-model="new_event.date_selector"
									:close-on-content-click="false"
									:nudge-right="40"
									transition="scale-transition"
									offset-y
									min-width="290px"
								>
									<template v-slot:activator="{ on, attrs }">
										<v-text-field label="Date" v-model="new_event.date" prepend-icon="mdi-calendar" v-bind="attrs" v-on="on"></v-text-field>
									</template>
									<v-date-picker v-model="new_event.date" @input="new_event.date_selector = false"></v-date-picker>
								</v-menu>
								</v-col>
								<v-col>
								<v-dialog ref="time_picker" v-model="new_event.time_picker" :return-value.sync="new_event.time" persistent width="290px">
									<template v-slot:activator="{ on, attrs }">
									<v-text-field
										v-model="new_event.time"
										label="Time"
										prepend-icon="mdi-clock-outline"
										readonly
										v-bind="attrs"
										v-on="on"
									></v-text-field>
									</template>
									<v-time-picker v-if="new_event.time_picker" v-model="new_event.time" full-width>
									<v-spacer></v-spacer>
									<v-btn text color="primary" @click="new_event.time_picker = false">
										Cancel
									</v-btn>
									<v-btn text color="primary" @click="$refs.time_picker.save(new_event.time)">
										OK
									</v-btn>
									</v-time-picker>
								</v-dialog>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
									<v-text-field label="Location" v-model="new_event.location"></v-text-field>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
									<v-textarea auto-grow v-model="new_event.description" label="Description" rows="3"><v-textarea>
								</v-col>
							</v-row>
						</v-card-text>
						<v-card-actions class="justify-end">
							<v-btn color="primary" @click="addEvent()">Add Event</v-btn>
						</v-card-actions>
					</v-card>
					</template>
				</v-dialog>
			<v-toolbar-items>
		</v-toolbar>
		<v-list three-line>
			<v-subheader><span v-if="group.upcoming && group.upcoming.length > 0" class="mr-1">{{ group.upcoming.length }}</span> Upcoming Events</v-subheader>
			<div v-show="group.upcoming && group.upcoming.length == 0">
			<v-list-item>
			<v-list-item-avatar>
			<v-icon>mdi-information</v-icon>
			</v-list-item-avatar>
			<v-list-item-content>
				<v-list-item-title>No upcoming events are scheduled.</v-list-item-title>
				<v-list-item-subtitle>.</v-list-item-subtitle>
			</v-list-item-content>
			</v-list-item>
			</div>
			<div v-for="event in group.upcoming">
			<v-list-item :href="groupLink( `${event.slug}` )" @click.prevent="goToPath( groupLink( `${ event.slug }` ) )">
			<v-list-item-avatar>
			<v-icon>mdi-calendar</v-icon>
			</v-list-item-avatar>
			<v-list-item-content>
				<v-list-item-title>{{ event.event_at | pretty_timestamp }}</v-list-item-title>
				<v-list-item-subtitle>{{ event.name }}</v-list-item-subtitle>
			</v-list-item-content>
			</v-list-item>
			</div>
			<v-subheader v-if="group.past && group.past.length != 0"><span v-show="group.past && group.past.length > 0" class="mr-1">{{ group.past.length }}</span> Past Events</v-subheader>
			<div v-for="event in group.past">
			<v-list-item :href="groupLink( `${event.slug}` )" @click.prevent="goToPath( groupLink( `${ event.slug }` ) )">
			<v-list-item-avatar>
			<v-icon>mdi-calendar</v-icon>
			</v-list-item-avatar>
			<v-list-item-content>
				<v-list-item-title>{{ event.event_at | pretty_timestamp }}</v-list-item-title>
				<v-list-item-subtitle>{{ event.name }}</v-list-item-subtitle>
			</v-list-item-content>
			</v-list-item>
			</div>
		</v-list>
		</v-card>
		<v-row v-show="organization.name">
		<v-col class="text-center">
			<v-btn text :href=`/${organization.slug}` @click.prevent="goToPath( `/${organization.slug}` )" class="mx-auto mt-7">Back to all groups</v-btn>
		</v-col>
		</v-row>
		</div>
	</v-card>

	<v-card v-if="route == 'event'" flat tile>
	<div style="max-width: 750px; margin: auto">
	<v-card class="my-5">
		<v-toolbar color="primary" dark flat>
			<v-toolbar-title>{{ event.name }}</v-toolbar-title>
			<v-spacer></v-spacer>
			<v-dialog max-width="600" v-model="edit_event.show" persistent>
					<template v-slot:activator="{ on, attrs }">
						<v-btn icon v-bind="attrs" v-on="on" @click="editEvent()" v-show="user.role == 'administrator' || group.owner"><v-icon large>mdi-pencil-box</v-icon></v-btn>
					</template>
					<template v-slot:default="dialog">
					<v-card>
						<v-toolbar color="primary" dark>
							<v-toolbar-title>Edit Event</v-toolbar-title>
							<v-spacer></v-spacer>
							<v-toolbar-items>
								<v-btn icon @click="dialog.value = false"><v-icon large>mdi-close-box</v-icon></v-btn>
							<v-toolbar-items>
						</v-toolbar>
						<v-card-text>
							<v-row no-gutters>
								<v-col class="mt-3">
									<v-text-field label="Name" v-model="edit_event.event.name" no-details></v-text-field>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
								<v-menu
									v-model="edit_event.date_selector"
									:close-on-content-click="false"
									:nudge-right="40"
									transition="scale-transition"
									offset-y
									min-width="290px"
								>
									<template v-slot:activator="{ on, attrs }">
										<v-text-field label="Date" v-model="edit_event.date" prepend-icon="mdi-calendar" v-bind="attrs" v-on="on"></v-text-field>
									</template>
									<v-date-picker v-model="edit_event.date" @input="edit_event.date_selector = false"></v-date-picker>
								</v-menu>
								</v-col>
								<v-col>
								<v-dialog ref="time_picker" v-model="edit_event.time_picker" :return-value.sync="edit_event.time" persistent width="290px">
									<template v-slot:activator="{ on, attrs }">
									<v-text-field
										v-model="edit_event.time"
										label="Time"
										prepend-icon="mdi-clock-outline"
										readonly
										v-bind="attrs"
										v-on="on"
									></v-text-field>
									</template>
									<v-time-picker v-if="edit_event.time_picker" v-model="edit_event.time" full-width>
									<v-spacer></v-spacer>
									<v-btn text color="primary" @click="edit_event.time_picker = false">
										Cancel
									</v-btn>
									<v-btn text color="primary" @click="$refs.time_picker.save(edit_event.time)">
										OK
									</v-btn>
									</v-time-picker>
								</v-dialog>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
									<v-text-field label="Location" v-model="edit_event.event.location"></v-text-field>
								</v-col>
							</v-row>
							<v-row no-gutters>
								<v-col>
									<v-textarea auto-grow v-model="edit_event.event.description_raw" label="Description" rows="3"><v-textarea>
								</v-col>
							</v-row>
							<v-alert type="error" v-for="error in edit_event.errors">{{ error }}</v-alert>
						</v-card-text>
						<v-card-actions class="justify-end">
							<v-btn text color="error" @click="deleteEvent()">Delete Event</v-btn>
							<v-btn color="primary" @click="updateEvent()">Update Event</v-btn>
						</v-card-actions>
					</v-card>
					</template>
				</v-dialog>
			</v-toolbar>
			<v-toolbar color="grey lighten-3" flat dense>
			<v-spacer></v-spacer>
			<v-toolbar-items>
			<v-menu v-model="attend_menu" :close-on-content-click="false" offset-y >
				<template v-slot:activator="{ on, attrs }">
					<v-btn color="grey lighten-3" tile depressed v-bind="attrs" v-on="on" v-show="event.status == 'upcoming' && user.username">RSVP to attend event <v-icon class="ml-1">mdi-calendar-check<v-icon></v-btn>
				</template>
				<v-card flat>
				<v-card-text class="py-0">
				<v-radio-group v-model="attend_selection" @change="attendEvent()">
					<v-radio label="Going" value="going"></v-radio>
					<v-radio label="Not Going" value="not-going"></v-radio>
				</v-radio-group>
				</v-card-text>
				</v-card>
			</v-menu>
			<v-dialog max-width="600" v-model="attend_event.show" persistent>
				<template v-slot:activator="{ on, attrs }">
					<v-btn color="grey lighten-3" tile depressed v-bind="attrs" v-on="on" v-show="event.status == 'upcoming' && ! user.username">RSVP to attend event <v-icon class="ml-1">mdi-calendar-check<v-icon></v-btn>
				</template>
				<template v-slot:default="dialog">
				<v-card>
					<v-toolbar color="primary" dark>
						<v-toolbar-title>Attend Event</v-toolbar-title>
						<v-spacer></v-spacer>
						<v-toolbar-items>
						<v-btn icon @click="attend_event.show = false"><v-icon>mdi-close</v-icon></v-btn>
						</v-toolbar-items>
					</v-toolbar>
					<v-card-text class="mt-5">
						<v-row no-gutters>
							<v-col><v-text-field v-model="attend_event.first_name" label="First Name" dense class="mb-2"></v-text-field></v-col>
							<v-col><v-text-field v-model="attend_event.last_name" label="Last Name" dense class="mb-2"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters>
							<v-col><v-text-field v-model="attend_event.email" label="Your Email" style="min-width: 220px" dense class="mb-2"></v-text-field></v-col>
						</v-row>
						<v-row no-gutters>
							<v-col><v-alert type="error" text dense v-for="error in attend_event.errors">{{ error }}</v-alert></v-col>
						</v-row>
						<v-row no-gutters>
							<v-col><v-btn @click=attendEventRequest() color="primary">Confirm attendance</v-btn></v-col>
						</v-row>
					</v-card-text>
				</v-card>
				</template>
			</v-dialog>
			</v-toolbar-items>
		</v-toolbar>
		<v-row>
			<v-col sm="8" cols="12">
				<v-list dense class="mb-3">
					<v-list-item>
					<v-list-item-icon>
					<v-icon>mdi-calendar</v-icon>
					</v-list-item-icon>
					<v-list-item-content>
					{{ event.event_at | pretty_timestamp }}
					</v-list-item-content>
					</v-list-item>
					<v-list-item>
					<v-list-item-icon>
					<v-icon>mdi-map-marker</v-icon> 
					</v-list-item-icon>
					<v-list-item-content>
					{{ event.location }}
					</v-list-item-content>
					</v-list-item>
				</v-list>
				<v-card-text v-html="event.description" class=""></v-card-text>
			</v-col>
			<v-col shrink sm="4" cols="12">
			<v-subheader v-if="event.status == 'upcoming'">Going</v-subheader>
			<v-subheader v-if="event.status == 'past'">Went</v-subheader>
			<v-divider></v-divider>
			<div v-for="attendee in event.attendees">
				<v-list-item>
				<v-list-item-avatar tile>
					<v-img :src="attendee.avatar"></v-img>
				</v-list-item-avatar>
				<v-list-item-content>
					<v-list-item-title>{{ attendee.name }}</v-list-item-title>
				</v-list-item-content>
				<v-list-item-action v-show="attendee.description">
				<v-tooltip bottom>
				<template v-slot:activator="{ on, attrs }">
					<v-icon color="grey lighten-1" v-bind="attrs" v-on="on">mdi-information</v-icon>
				</template>
				<span>{{ attendee.description }}</span>
				</v-tooltip>
				</v-list-item-action>
				</v-list-item>
				<v-divider></v-divider>
			</div>

			<div v-if="event.status == 'upcoming' && event.attendees_not.length > 0">
			<v-subheader>Not Going</v-subheader>
			<v-divider></v-divider>
			<div v-for="attendee in event.attendees_not">
				<v-list-item>
				<v-list-item-avatar tile>
					<v-img :src="attendee.avatar"></v-img>
				</v-list-item-avatar>
				<v-list-item-content>
					<v-list-item-title>{{ attendee.name }}</v-list-item-title>
					<v-list-item-subtitle></v-list-item-subtitle>
				</v-list-item-content>
				</v-list-item>
				<v-divider></v-divider>
			</div>
			</div>
			</v-col>
	</v-card>
	<v-btn :href="groupHomeLink( group )" @click.prevent="goToPath( groupHomeLink( group ) )" class="my-3"><v-icon class="mr-1">mdi-arrow-left-box</v-icon> All events</v-btn>
	</div>
	</v-card>
	
	<v-card v-if="route == 'start-group'" class="text-center" flat>
		<v-container style="max-width: 500px;" class="text-left">
			<v-text-field label="Group Name" v-model="group_new.name"></v-text-field>
			<v-text-field label="Owner's email address" v-model="group_new.email" persistent-hint hint="Will send a verification email before group is created."></v-text-field>
			<v-textarea auto-grow rows="1" label="Description" v-model="group_new.description"></v-textarea>
			<v-alert type="error" v-for="error in group_new.errors">{{ error }}</v-alert>
			<v-btn @click="createGroup()">Create group</v-btn>
		</v-container>
	</v-card>
	<v-card flat v-if="route == 'find-group'">
	<v-container style="max-width: 500px;">
	<v-text-field label="Find a group." solo v-model="group_search" class="mt-5"></v-text-field>
	<v-data-table :items="groups" :search="group_search" :headers="[{ text: 'Name', value: 'name' }]" hide-default-header hide-default-footer class="minimal">
	<template v-slot:body="{ items }">
        <tbody>
          <tr v-for="item in items" :key="item.group_id">
            <td>
			<v-hover v-slot="{ hover }">
			<v-card tile class="mx-auto my-3" max-width="500" :elevation="hover ? 5 : 2" :href="groupHomeLink( item )" @click.prevent="goToPath( groupHomeLink( item ) )">
				<v-card-title>{{ item.name }}</v-card-title>
				<v-card-text>
					{{ item.description }}
				</v-card-text>
				<v-card-text v-show="item.stats.members || item.stats.events">
				<span v-if="item.stats.members">{{ item.stats.members }} members</span><span v-show="item.stats.members && item.stats.events">, </span><span v-if="item.stats.events">{{ item.stats.events }} events</span>
				</v-card-text>
			</v-card>
			</v-hover>
			</td>
          </tr>
        </tbody>
      </template>
	</v-data-table>
	</v-container>
	</v-card>
	<v-card v-if="route == 'profile'" flat tile>
		<v-toolbar color="grey lighten-4" light flat>
			<v-toolbar-title>Edit profile</v-toolbar-title>
			<v-spacer></v-spacer>
			<v-toolbar-items>
			</v-toolbar-items>
		</v-toolbar>
		<v-card-text style="max-width:480px">
			<v-row>
				<v-col cols="12">
				<v-list>
				<v-list-item link href="https://gravatar.com" target="_blank">
					<v-list-item-avatar tile>
						<v-img :src="user.avatar"></v-img>
					</v-list-item-avatar>
					<v-list-item-content>
						<v-list-item-title>Edit thumbnail with Gravatar</v-list-item-title>
					</v-list-item-content>
					<v-list-item-icon>
						<v-icon>mdi-open-in-new</v-icon>
					</v-list-item-icon>
				</v-list-item>
				</v-list>
				<v-text-field :value="user.name" @change.native="user.name = $event.target.value" label="Display Name"></v-text-field>
				<v-text-field :value="user.username" label="Username" readonly disabled></v-text-field>
				<v-text-field :value="user.email" @change.native="user.email = $event.target.value" label="Email"></v-text-field>
				<v-text-field :value="user.new_password" @change.native="user.new_password = $event.target.value" type="password" label="New Password" hint="Leave empty to keep current password." persistent-hint></v-text-field>
				</v-col>
				<v-col cols="12" class="mt-3">
					<v-alert text :value="true" type="error" v-for="error in user.errors" class="mt-5">{{ error }}</v-alert>
					<v-alert text :value="true" type="success" v-show="user.success" class="mt-5">{{ user.success }}</v-alert>
					<v-btn color="primary" dark @click="updateAccount()">Save Account</v-btn>
				</v-col>
			</v-layout>
	</v-card>
	<v-snackbar :timeout="3000" :multi-line="true" v-model="snackbar.show">
		{{ snackbar.message }}
		<v-btn dark text @click.native="snackbar.show = false">Close</v-btn>
	</v-snackbar>
	</v-main>
</v-app>
</div>
<script src="https://cdn.jsdelivr.net/npm/vue@2.x/dist/vue.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@2.x/dist/vuetify.js"></script>
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
		drawer: null,
		logged_in: false,
		wp_nonce: "",
		attend_menu: "",
		attend_selection: "",
		event: {},
		new_event: { show: false, time: "", date: "", time_picker: false, date_selector: false, name: "", location: "", group_id: "", description: "" },
		edit_event: { show: false, time: "", date: "", time_picker: false, date_selector: false, errors: [], event: {} },
		attend_event: { show: false, event_id: "", first_name: "", last_name: "", email: "", errors: [] },
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
			axios.post( '/wp-json/localmeet/v1/groups/create', {
				'request': this.group_new,
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
			});
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
			this.new_event.group_id = this.group.group_id
			event_id = this.edit_event.event.event_id
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
			confirm = confirm("Delete event?")
			if ( ! confirm ) {
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