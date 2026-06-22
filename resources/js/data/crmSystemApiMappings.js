/**
 * Integrated system API (vendor CRM) ↔ Application API routes under /api/crm (parity).
 * Keys are integration `slug` values from the database (Laravel Str::slug of name).
 *
 * Request/response shapes mirror GhlCompatController parity responses.
 */

const GHL_BASE = 'https://services.leadconnectorhq.com';

/** Documentation samples only — substitute ids from CRM list/search responses. */
const GHL_SAMPLE_CONTACT_ID = '550e8400-e29b-41d4-a716-446655440000';
const GHL_SAMPLE_NOTE_ID = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';

/** @typedef {{ query?: Record<string, unknown> | null, path?: Record<string, unknown> | null, body?: Record<string, unknown> | null }} MappedApiRequest */

/**
 * @typedef {{
 *   key: string,
 *   name: string,
 *   crmMethod: string,
 *   crmPath: string,
 *   crmBaseUrl?: string,
 *   systemMethod: string,
 *   systemPath: string,
 *   routeName?: string,
 *   headersNote?: string,
 *   request: MappedApiRequest,
 *   responseStatus?: number,
 *   responseBody: Record<string, unknown>,
 * }} CrmMappedEndpoint
 */

/** @type {Record<string, CrmMappedEndpoint[]>} */
export const CRM_SYSTEM_API_MAPPINGS_BY_SLUG = {
	gohighlevel: [
		{
			key: 'ghl.contacts.index',
			name: 'List contacts',
			crmMethod: 'GET',
			crmPath: '/contacts/',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'GET',
			systemPath: '/api/crm/contacts',
			routeName: 'api.crm.contacts.index',
			headersNote:
				'Integrated system: Bearer (OAuth access token) + Version 2021-07-28. Application API: Bearer (API JWT from this app).',
			request: {
				query: { locationId: 'loc_xxx', limit: 20 },
				path: null,
				body: null,
			},
			responseStatus: 200,
			responseBody: {
				success: true,
				contacts: [],
				meta: { total: 0, nextPageUrl: null },
			},
		},
		{
			key: 'ghl.contacts.search',
			name: 'Search contacts',
			crmMethod: 'POST',
			crmPath: '/contacts/search',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'POST',
			systemPath: '/api/crm/contacts/search',
			routeName: 'api.crm.contacts.search',
			headersNote:
				'Integrated system: Bearer + Version header. Application API: Bearer (API JWT). Content-Type: application/json.',
			request: {
				query: null,
				path: null,
				body: { locationId: 'loc_xxx', query: 'jane@example.com', pageLimit: 20 },
			},
			responseStatus: 200,
			responseBody: { success: true, contacts: [], total: 0 },
		},
		{
			key: 'ghl.users.index',
			name: 'List users',
			crmMethod: 'GET',
			crmPath: '/users/',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'GET',
			systemPath: '/api/crm/users',
			routeName: 'api.crm.users.index',
			headersNote: 'Integrated system: Authorization + Version header. Application API: Bearer (API JWT).',
			request: {
				query: { locationId: 'loc_xxx' },
				path: null,
				body: null,
			},
			responseStatus: 200,
			responseBody: { success: true, users: [] },
		},
		{
			key: 'ghl.tags',
			name: 'List location tags',
			crmMethod: 'GET',
			crmPath: '/locations/{locationId}/tags',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'GET',
			systemPath: '/api/crm/tags',
			routeName: 'api.crm.tags.index',
			headersNote: 'Integrated system: Authorization + Version header. Application API: Bearer (API JWT).',
			request: {
				query: null,
				path: { locationId: 'loc_xxx' },
				body: null,
			},
			responseStatus: 200,
			responseBody: { tags: [], meta: {} },
		},
		{
			key: 'ghl.contacts.tags.store',
			name: 'Add, update, or remove contact tags',
			crmMethod: 'POST',
			crmPath: '/contacts/{contactId}/tags',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'POST',
			systemPath: '/api/crm/contacts/add/tags',
			routeName: 'api.crm.contacts.tags.store',
			headersNote:
				'Integrated system: Authorization + Version header. Application API: Bearer (API JWT). Content-Type: application/json. Use this endpoint for add/update/remove contact tag changes.',
			request: {
				query: null,
				path: null,
				body: { contactid: GHL_SAMPLE_CONTACT_ID, tags: ['tag_id_1', 'tag_id_2'] },
			},
			responseStatus: 200,
			responseBody: {
				tags: ['sent whatsapp', 'friendly', 'hni'],
				message: 'tags updated',
				status: true,
			},
		},
		{
			key: 'ghl.contacts.notes.index',
			name: 'List contact notes',
			crmMethod: 'GET',
			crmPath: '/contacts/{contactId}/notes',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'GET',
			systemPath: '/api/crm/contacts/notes/list',
			routeName: 'api.crm.contacts.notes.index',
			headersNote: 'Integrated system: Authorization + Version header. Application API: Bearer (API JWT).',
			request: {
				query: { contactId: GHL_SAMPLE_CONTACT_ID },
				path: null,
				body: null,
			},
			responseStatus: 200,
			responseBody: {
				success: true,
				status: true,
				notes: [
					{
						id: GHL_SAMPLE_NOTE_ID,
						body: 'Call: INCOMING\nNumber: +919910023290\nContact: Ankur Wadhawan\nDuration: 18s\nAt: 2026-05-06 02:46:31',
						bodyText:
							'Call: INCOMING\nNumber: +919910023290\nContact: Ankur Wadhawan\nDuration: 18s\nAt: 2026-05-06 02:46:31',
						attachments: [],
						title: '',
						user_name: 'Excellanto Developers',
						userId: 'TlWn93srwc6WyxUYy98a',
						contactId: GHL_SAMPLE_CONTACT_ID,
						dateAdded: '2026-05-05T21:16:56.211Z',
						dateUpdated: '',
					},
				],
				meta: {},
			},
		},
		{
			key: 'ghl.contacts.notes.store',
			name: 'Create contact note',
			crmMethod: 'POST',
			crmPath: '/contacts/{contactId}/notes',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'POST',
			systemPath: '/api/crm/contacts/{contactId}/notes',
			routeName: 'api.crm.contacts.notes.store',
			headersNote:
				'Integrated system: Authorization + Version header. Application API: Bearer (API JWT). Content-Type: application/json.',
			request: {
				query: null,
				path: { contactId: GHL_SAMPLE_CONTACT_ID },
				body: { body: 'Follow-up next week.' },
			},
			responseStatus: 200,
			responseBody: {
				success: true,
				message: 'Note created.',
				note: {
					id: GHL_SAMPLE_NOTE_ID,
					contactId: GHL_SAMPLE_CONTACT_ID,
					body: 'Follow-up next week.',
				},
			},
		},
		{
			key: 'ghl.contacts.notes.update',
			name: 'Update contact note',
			crmMethod: 'PUT',
			crmPath: '/contacts/{contactId}/notes/{noteId}',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'POST',
			systemPath: '/api/crm/contacts/notes/update',
			routeName: 'api.crm.contacts.notes.update',
			headersNote:
				'Integrated system: Authorization + Version header. Application API: Bearer (API JWT). Content-Type: application/json.',
			request: {
				query: null,
				path: null,
				body: {
					noteid: GHL_SAMPLE_NOTE_ID,
					user_id: 10,
					contactId: GHL_SAMPLE_CONTACT_ID,
					body: '2 NOW Prospect wanted to schedule call on Friday at 11:30 AM',
					urls: ['https://your-s3-bucket.com/uploads/audio_brief.mp3'],
				},
			},
			responseStatus: 200,
			responseBody: {
				success: true,
				status: true,
				notes: [
					{
						id: GHL_SAMPLE_NOTE_ID,
						contactId: GHL_SAMPLE_CONTACT_ID,
						body: '2 NOW Prospect wanted to schedule call on Friday at 11:30 AM',
						attachments: ['https://your-s3-bucket.com/uploads/audio_brief.mp3'],
					},
				],
			},
		},
		{
			key: 'ghl.contacts.notes.destroy',
			name: 'Delete contact note',
			crmMethod: 'DELETE',
			crmPath: '/contacts/{contactId}/notes/{noteId}',
			crmBaseUrl: GHL_BASE,
			systemMethod: 'POST',
			systemPath: '/api/crm/contacts/notes/delete',
			routeName: 'api.crm.contacts.notes.destroy',
			headersNote:
				'Integrated system: Authorization + Version header. Application API: Bearer (API JWT). Content-Type: application/json.',
			request: {
				query: null,
				path: null,
				body: {
					noteid: 'jyukCGNByCVyoOfXMOjm',
					user_id: 6,
					contactId: 'o4gTsYqevQpfwrjzfMFN',
				},
			},
			responseStatus: 200,
			responseBody: {
				success: true,
				message: 'Note deleted.',
				contactId: GHL_SAMPLE_CONTACT_ID,
				noteId: GHL_SAMPLE_NOTE_ID,
			},
		},
	],
};

/**
 * @param {string | undefined | null} slug
 * @returns {CrmMappedEndpoint[]}
 */
export function getCrmSystemApiMappingsForSlug(slug) {
	if (!slug || typeof slug !== 'string') return [];
	return CRM_SYSTEM_API_MAPPINGS_BY_SLUG[slug] ?? [];
}
