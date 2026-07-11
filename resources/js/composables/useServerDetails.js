import { ref } from 'vue';
import axios from 'axios';

// Client-side cache: Map<serverId, { data, timestamp }>
const cache = new Map();
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

export function useServerDetails() {
    const databases = ref([]);
    const services = ref([]);
    const isLoading = ref(false);
    const error = ref(null);

    /**
     * Fetch server details (databases & services).
     * Returns cached data if available and not expired.
     */
    const fetchDetails = async (serverId, forceRefresh = false) => {
        if (!serverId) return;

        // Check client cache
        if (!forceRefresh && cache.has(serverId)) {
            const cached = cache.get(serverId);
            if (Date.now() - cached.timestamp < CACHE_TTL) {
                databases.value = cached.data.databases;
                services.value = cached.data.services;
                return;
            }
            // Expired — remove stale entry
            cache.delete(serverId);
        }

        databases.value = [];
        services.value = [];
        isLoading.value = true;
        error.value = null;

        try {
            const url = forceRefresh 
                ? route('servers.details', { server: serverId, force: 1 })
                : route('servers.details', serverId);
                
            const response = await axios.get(url);
            const data = response.data;

            databases.value = data.databases;
            services.value = data.services;

            // Store in client cache
            cache.set(serverId, {
                data,
                timestamp: Date.now(),
            });
        } catch (err) {
            error.value = 'Failed to load server details.';
            console.error('Error fetching server details:', err);
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Invalidate cache for a specific server.
     * Call this after creating/updating/deleting a database or service.
     */
    const invalidateCache = (serverId) => {
        cache.delete(serverId);
    };

    /**
     * Clear entire client cache.
     */
    const clearCache = () => {
        cache.clear();
    };

    return {
        databases,
        services,
        isLoading,
        error,
        fetchDetails,
        invalidateCache,
        clearCache,
    };
}
