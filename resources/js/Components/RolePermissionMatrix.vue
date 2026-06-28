<template>
	<div class="space-y-4">
		<div v-for="(content, module) in matrix" :key="module" class="rounded border border-gray-200 bg-white">
			<div class="flex items-center border-b px-4 py-2">
				<button
					type="button"
					class="flex flex-1 items-center justify-between text-left font-medium text-gray-800 hover:text-gray-900"
					@click="expanded[module] = !expanded[module]"
				>
					<span>{{ module }}</span>
					<svg
						class="h-5 w-5 transition-transform"
						:class="{ 'rotate-180': expanded[module] }"
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 20 20"
						fill="currentColor"
					>
						<path
							fill-rule="evenodd"
							d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
							clip-rule="evenodd"
						/>
					</svg>
				</button>

				<label
					v-if="getModuleNavPermission(module)"
					class="ml-4 flex shrink-0 items-center gap-2 text-xs text-gray-600"
				>
					<span>Show in navigation</span>
					<input
						type="checkbox"
						class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
						:checked="modelValue.includes(getModuleNavPermission(module))"
						@click.stop
						@change="toggle(getModuleNavPermission(module), $event.target.checked)"
					/>
				</label>
			</div>
			<div v-show="expanded[module]" class="p-4">
				<!-- Nested structure (User Management, Settings with sub-headings) -->
				<template v-if="isNested(content)">
					<div v-for="(perms, subHeading) in content" :key="subHeading" class="mb-4 last:mb-0">
						<div class="mb-2 flex items-center justify-between">
							<div class="text-sm font-medium text-gray-600">{{ subHeading }}</div>
							<label
								v-if="getSubModuleNavPermission(module, subHeading)"
								class="ml-4 flex items-center gap-2 text-xs text-gray-600"
							>
								<span>Show in navigation</span>
								<input
									type="checkbox"
									class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
									:checked="modelValue.includes(getSubModuleNavPermission(module, subHeading))"
									@click.stop
									@change="toggle(getSubModuleNavPermission(module, subHeading), $event.target.checked)"
								/>
							</label>
						</div>
						<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
							<label v-for="p in permissionsForSubgrid(module, subHeading, perms)" :key="p.name" class="flex items-center gap-2">
								<input type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
									:checked="modelValue.includes(p.name)"
									@change="toggle(p.name, $event.target.checked)" />
								<span class="text-sm text-gray-700">{{ getPermissionLabel(p.name) }}</span>
							</label>
						</div>
					</div>
				</template>
				<!-- Simple structure (flat list of permissions) -->
				<template v-else>
					<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
						<label v-for="p in content" :key="p.name" class="flex items-center gap-2">
							<input type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
								:checked="modelValue.includes(p.name)"
								@change="toggle(p.name, $event.target.checked)" />
							<span class="text-sm text-gray-700">{{ getPermissionLabel(p.name) }}</span>
						</label>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
	matrix: { type: Object, required: true },
	modelValue: { type: Array, default: () => [] }
});
const emit = defineEmits(['update:modelValue']);

const expanded = ref({});
const moduleNavPermissionMap = {
	'Api Management': 'nav.api-management.show',
	CRM: 'nav.crm.show',
	'Contact Management': 'nav.contact-management.show',
	'Url Management': 'nav.url-management.show',
	'User Management': 'nav.user-management.show',
	Settings: 'nav.settings.show',
	Configurations: 'nav.configurations.show',
};
const permissionLabels = {
	'users.view': 'View users',
	'users.create': 'Create users',
	'users.update': 'Edit users',
	'users.delete': 'Delete users',
	'roles.view': 'View roles',
	'roles.create': 'Create roles',
	'roles.update': 'Edit roles',
	'roles.delete': 'Delete roles',
	'permissions.view': 'View permissions',
	'permissions.create': 'Create permissions',
	'permissions.update': 'Edit permissions',
	'permissions.delete': 'Delete permissions',
	'activity-logs.view': 'View activity logs',
	'activity-logs.export': 'Export activity logs',
	'activity-logs.delete': 'Delete activity logs',
	'call-logs.view': 'View call logs',
	'call-logs.delete': 'Delete call logs',
	'contacts.view': 'View contacts',
	'contacts.create': 'Create contacts',
	'contacts.update': 'Edit contacts',
	'contacts.delete': 'Delete contacts',
	'short-urls.view': 'View short URLs',
	'settings.view': 'View site settings',
	'settings.update': 'Edit site settings',
	'languages.view': 'View languages',
	'languages.update': 'Edit languages',
	'languages.sync': 'Sync language files',
	'ai-settings.view': 'View AI settings',
	'ai-settings.update': 'Edit AI settings',
	// email account management removed
	'tenants.view': 'View tenants',
	'tenants.update': 'Edit tenants',
	'dashboard.view': 'View dashboard',
	'admin-panel-access': 'Admin panel access',
};
const subModuleNavPermissionMap = {
	'Api Management': {
		'API Endpoint Mapper': 'nav.api-management.api-endpoint-mapper.show',
		'Mapped APIs': 'nav.api-management.mapped-apis.show'
	},
	CRM: {
		'Call Logs': 'nav.crm.call-logs.show'
	},
	'Contact Management': {
		Contacts: 'nav.contact-management.contacts.show'
	},
	'User Management': {
		'Users': 'nav.user-management.users.show',
		'Assignment rules': 'nav.user-management.assignment-rules.show',
		'Roles': 'nav.user-management.roles.show',
		'Permissions': 'nav.user-management.permissions.show',
		'Activity': 'nav.user-management.activity-logs.show',
		'Tenants': 'nav.user-management.tenants.show'
	},
	'Settings': {
		'Languages': 'nav.settings.languages.show',
		'Data Configuration': 'nav.settings.data-configuration.show',
		'Integrations': 'nav.settings.integrations.show',
		'Email Templates': 'nav.settings.email-templates.show',
		'System settings': 'nav.settings.system-settings.show'
	},
	// Configurations / Email management removed
};

function isNested(content) {
	if (!content || typeof content !== 'object') return false;
	if (Array.isArray(content)) return false;
	// Nested: sub-heading object; every value is an array of permission rows (empty allowed)
	const entries = Object.entries(content);
	if (entries.length === 0) return false;
	return entries.every(([, v]) => {
		if (!Array.isArray(v)) return false;
		if (v.length === 0) return true;
		return typeof v[0]?.name === 'string';
	});
}

watch(() => props.matrix, (matrix) => {
	const initial = {};
	for (const key of Object.keys(matrix || {})) {
		initial[key] = true;
	}
	expanded.value = initial;
}, { immediate: true });

function toggle(name, checked) {
	const next = new Set(props.modelValue);
	if (checked) {
		next.add(name);
	} else {
		next.delete(name);
		// Unchecking a module parent nav removes all child nav.* under that module (matches sidebar semantics).
		const cascade = [
			['nav.api-management.show', 'nav.api-management.'],
			['nav.crm.show', 'nav.crm.'],
			['nav.user-management.show', 'nav.user-management.'],
			['nav.settings.show', 'nav.settings.'],
			['nav.configurations.show', 'nav.configurations.'],
		];
		for (const [parent, prefix] of cascade) {
			if (name === parent) {
				for (const n of [...next]) {
					if (n.startsWith(prefix) && n !== parent) {
						next.delete(n);
					}
				}
				break;
			}
		}
		// Tenants: keep nav flag and capability perms aligned so sidebar matches the matrix.
		if (!checked && name === 'nav.user-management.tenants.show') {
			next.delete('tenants.view');
			next.delete('tenants.update');
		}
		if (!checked && (name === 'tenants.view' || name === 'tenants.update')) {
			next.delete('nav.user-management.tenants.show');
		}
	}
	emit('update:modelValue', Array.from(next));
}

function getModuleNavPermission(module) {
	return moduleNavPermissionMap[module] || null;
}

function getSubModuleNavPermission(module, subHeading) {
	return subModuleNavPermissionMap[module]?.[subHeading] || null;
}

/** Hide only the subsection nav.* from the grid (it has its own "Show in navigation" toggle). Capability CRUD stays in the grid. */
function permissionsForSubgrid(module, subHeading, perms) {
	const childNav = getSubModuleNavPermission(module, subHeading);
	if (!childNav) {
		return perms;
	}
	return perms.filter((p) => p?.name !== childNav);
}

function getPermissionLabel(name) {
	return permissionLabels[name] || name;
}
</script>
