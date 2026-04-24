import { api } from './services/api';
import { tokenStore } from './services/token';

export function loginPage() {
    return {
        form: {
            username: '',
            password: '',
            device_name: 'Admin Panel Browser',
        },
        loading: false,
        message: '',

        init() {
            if (tokenStore.get()) {
                window.location.assign('/admin/dashboard');
            }
        },

        async submit() {
            this.loading = true;
            this.message = '';

            try {
                const response = await api.post('/api/v1/auth/login', this.form);
                const payload = response.data;

                tokenStore.set(payload.access_token);
                tokenStore.setUser(payload.user);
                api.setToken(payload.access_token);

                window.location.assign(payload.user?.role === 'technician'
                    ? '/admin/dashboard'
                    : '/admin/dashboard');
            } catch (error) {
                this.message = error.message || 'Login gagal.';
            } finally {
                this.loading = false;
            }
        },
    };
}
