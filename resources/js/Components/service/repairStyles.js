import { Gamepad2, Headphones, Laptop, Smartphone, Tablet, Watch, Wrench } from 'lucide-vue-next';

const STYLES = {
    received: { dot: 'bg-slate-400', bg: 'bg-slate-100 dark:bg-slate-800', fg: 'text-slate-600 dark:text-slate-300', badge: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' },
    diagnosing: { dot: 'bg-sky-500', bg: 'bg-sky-100 dark:bg-sky-900/40', fg: 'text-sky-700 dark:text-sky-300', badge: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200' },
    awaiting_approval: { dot: 'bg-orange-500', bg: 'bg-orange-100 dark:bg-orange-900/40', fg: 'text-orange-700 dark:text-orange-300', badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200' },
    awaiting_parts: { dot: 'bg-amber-500', bg: 'bg-amber-100 dark:bg-amber-900/40', fg: 'text-amber-700 dark:text-amber-300', badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' },
    in_progress: { dot: 'bg-indigo-500', bg: 'bg-indigo-100 dark:bg-indigo-900/40', fg: 'text-indigo-700 dark:text-indigo-300', badge: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' },
    ready: { dot: 'bg-emerald-500', bg: 'bg-emerald-100 dark:bg-emerald-900/40', fg: 'text-emerald-700 dark:text-emerald-300', badge: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' },
    collected: { dot: 'bg-gray-400', bg: 'bg-gray-100 dark:bg-gray-800', fg: 'text-gray-500', badge: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' },
    cancelled: { dot: 'bg-red-500', bg: 'bg-red-100 dark:bg-red-900/40', fg: 'text-red-600 dark:text-red-300', badge: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-200' },
};

export const statusStyle = (status) => STYLES[status] || STYLES.received;

const ICONS = { phone: Smartphone, tablet: Tablet, laptop: Laptop, watch: Watch, earbuds: Headphones, console: Gamepad2 };
export const deviceIcon = (type) => ICONS[type] || Wrench;

/** The happy-path order shown as a stepper on the job page. */
export const FLOW = ['received', 'diagnosing', 'awaiting_approval', 'in_progress', 'ready', 'collected'];

/** WhatsApp message templates per status. */
export const whatsappTemplate = (job, shop, trackUrl) => {
    const name = job.customer?.name || 'there';
    const base = `Hi ${name}, this is ${shop.name}. `;
    const ref = `(Job ${job.job_number}, ${job.device})`;
    const balance = Math.max(0, Number(job.quoted_total || 0) - Number(job.deposit || 0)).toFixed(2);
    const track = trackUrl ? `\nTrack status: ${trackUrl}?job=${job.job_number}` : '';
    switch (job.status) {
        case 'received':
            return `${base}We've received your device ${ref}. We'll update you after diagnosis.${track}`;
        case 'diagnosing':
            return `${base}Your device ${ref} is being diagnosed by our technician.${track}`;
        case 'awaiting_approval':
            return `${base}Diagnosis for ${ref}: ${job.diagnosis || '-'}\nQuoted total: RM ${Number(job.quoted_total || 0).toFixed(2)}. Please reply YES to proceed with the repair.`;
        case 'awaiting_parts':
            return `${base}We're waiting for parts for your device ${ref}. We'll let you know once they arrive.${track}`;
        case 'in_progress':
            return `${base}Your device ${ref} is now being repaired.${track}`;
        case 'ready':
            return `${base}Good news! Your device ${ref} is ready for collection. Balance: RM ${balance}. Please bring your job sheet.${shop.phone ? ` Call ${shop.phone} for enquiries.` : ''}`;
        case 'collected':
            return `${base}Thank you for collecting your device ${ref}. Your repair comes with ${job.warranty_days} days warranty.`;
        default:
            return `${base}Update on your device ${ref}: ${job.status}.`;
    }
};
