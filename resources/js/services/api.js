import { tokenStore } from './token';

export class ApiError extends Error {
    constructor(message, status, errors = {}, meta = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
        this.meta = meta;
    }
}

export const api = {
    token: tokenStore.get(),

    setToken(token) {
        this.token = token;
    },

    async get(path, params = {}) {
        return this.request('GET', path, { params });
    },

    async post(path, body = {}) {
        return this.request('POST', path, { body });
    },

    async patch(path, body = {}) {
        return this.request('PATCH', path, { body });
    },

    async put(path, body = {}) {
        return this.request('PUT', path, { body });
    },

    async delete(path) {
        return this.request('DELETE', path);
    },

    async request(method, path, options = {}) {
        const url = new URL(path, window.location.origin);
        const params = options.params ?? {};

        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, value);
            }
        });

        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.token) {
            headers.Authorization = `Bearer ${this.token}`;
        }

        const fetchOptions = {
            method,
            headers,
        };

        if (!['GET', 'HEAD'].includes(method) && options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            fetchOptions.body = JSON.stringify(options.body);
        }

        let response;

        try {
            response = await fetch(url, fetchOptions);
        } catch (error) {
            throw new ApiError('Tidak bisa menghubungi server API.', 0, { network: [error.message] });
        }

        const envelope = await response.json().catch(() => ({
            success: false,
            message: 'Server mengirim response yang tidak valid.',
            errors: {},
            meta: {},
        }));

        if (response.status === 401) {
            tokenStore.forget();
            if (!window.location.pathname.startsWith('/login')) {
                window.location.assign('/login');
            }
        }

        if (!response.ok) {
            throw new ApiError(
                envelope.message || 'Request gagal.',
                response.status,
                envelope.errors || {},
                envelope.meta || {},
            );
        }

        return envelope;
    },
};
