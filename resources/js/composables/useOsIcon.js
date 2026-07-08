import { 
    Server, 
    MonitorSmartphone,
    Terminal,
    HardDrive,
    Cloud,
} from 'lucide-vue-next';

/**
 * Get the appropriate icon component for an operating system
 * @param {string} osName - Name of the operating system
 * @returns {Component} - Vue component for the icon
 */
export function getOsIcon(osName) {
    const osMap = {
        'Ubuntu': Terminal,
        'Debian': Terminal,
        'CentOS': Terminal,
        'Windows': MonitorSmartphone,
        'Other': HardDrive,
    };

    return osMap[osName] || Server;
}

/**
 * Get the appropriate icon color class for an operating system
 * @param {string} osName - Name of the operating system
 * @returns {string} - Tailwind CSS color class
 */
export function getOsIconColor(osName) {
    const colorMap = {
        'Ubuntu': 'text-orange-500',
        'Debian': 'text-red-500',
        'CentOS': 'text-purple-500',
        'Windows': 'text-blue-500',
        'Other': 'text-muted-foreground',
    };

    return colorMap[osName] || 'text-muted-foreground';
}
