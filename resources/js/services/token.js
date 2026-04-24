const TOKEN_KEY = 'feralix.billing.token';
const USER_KEY = 'feralix.billing.user';

export const tokenStore = {
    get() {
        return window.localStorage.getItem(TOKEN_KEY);
    },

    set(token) {
        window.localStorage.setItem(TOKEN_KEY, token);
    },

    forget() {
        window.localStorage.removeItem(TOKEN_KEY);
        window.localStorage.removeItem(USER_KEY);
    },

    user() {
        const payload = window.localStorage.getItem(USER_KEY);

        if (!payload) {
            return null;
        }

        try {
            return JSON.parse(payload);
        } catch {
            return null;
        }
    },

    setUser(user) {
        window.localStorage.setItem(USER_KEY, JSON.stringify(user));
    },
};
