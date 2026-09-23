import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

/** Surface the session `success` / `error` flash props as toasts. */
export function useFlashToast() {
    const page = usePage();
    watch(
        () => [page.props.success, page.props.error],
        ([success, error]) => {
            if (success) toast.success(success, { autoClose: 2500 });
            if (error) toast.error(error, { autoClose: 5000 });
        },
        { immediate: true },
    );
}

export const money = (v) =>
    Number(v || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/** wa.me link for a Malaysian number stored as +60… */
export const whatsappUrl = (phone, text = '') => {
    const digits = String(phone || '').replace(/\D+/g, '');
    if (!digits) return null;
    return `https://wa.me/${digits}${text ? `?text=${encodeURIComponent(text)}` : ''}`;
};
