// Simple API client wrapper
const ApiClient = {
    async request({ url, method = "GET", data = null, csrfToken = null }) {
        try {
            const options = {
                method,
                credentials: "include",
                headers: {
                    "Content-Type": "application/json"
                }
            };

            if (data) {
                options.body = JSON.stringify(data);
            }

            // Optional CSRF support (future-proof)
            if (csrfToken) {
                options.headers["X-CSRF-Token"] = csrfToken;
            }

            const response = await fetch(url, options);

            // Handle non-JSON or server errors
            let result;
            try {
                result = await response.json();
            } catch (e) {
                throw new Error("Invalid JSON response from server");
            }

            return result; // RAW response (as requested)
        } catch (error) {
            console.error("API Client Error:", error);
            throw error;
        }
    },

    get(url) {
        return this.request({ url });
    },

    post(url, data, csrfToken = null) {
        return this.request({
            url,
            method: "POST",
            data,
            csrfToken
        });
    }
};