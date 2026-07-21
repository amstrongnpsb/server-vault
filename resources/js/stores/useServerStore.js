import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useServerStore = defineStore('server', () => {
    const servers = ref({});

    const onlineCount = computed(() =>
        Object.values(servers.value).filter(s => s.status === 'Online').length
    );

    const offlineCount = computed(() =>
        Object.values(servers.value).filter(s => s.status === 'Offline').length
    );

    function updateStatus(id, status, lastCheckedAt) {
        if (servers.value[id]) {
            servers.value[id].status = status;
            servers.value[id].last_checked_at = lastCheckedAt;
        }
    }

    function setServers(list) {
        const map = {};
        list.forEach(s => { map[s.id] = s; });
        servers.value = map;
    }

    return { servers, onlineCount, offlineCount, updateStatus, setServers };
});
