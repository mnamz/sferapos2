<template>
    <Head :title="job.job_number" />
    <AppLayout
        :breadcrumbs="[
            { name: 'Repairs', href: route('repairs.index') },
            { name: job.job_number, href: route('repairs.show', job.id) },
        ]"
    >
        <div class="mx-auto max-w-7xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex flex-col justify-between gap-3 rounded-xl bg-white p-4 shadow-sm lg:flex-row lg:items-center dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div :class="['flex h-12 w-12 items-center justify-center rounded-xl', statusStyle(job.status).bg]">
                        <component :is="deviceIcon(job.device_type)" :class="['h-6 w-6', statusStyle(job.status).fg]" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="font-mono text-xl font-bold text-gray-900 dark:text-white">{{ job.job_number }}</h1>
                            <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', statusStyle(job.status).badge]">{{ statuses[job.status] }}</span>
                            <span v-if="job.priority === 'urgent'" class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 uppercase">Urgent</span>
                            <span v-if="job.under_warranty" class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 uppercase">Warranty until {{ job.warranty_expires_at }}</span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            {{ job.device }}<span v-if="job.color"> · {{ job.color }}</span><span v-if="job.imei" class="font-mono"> · {{ job.imei }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a :href="route('repairs.print', job.id)" target="_blank" class="btn-secondary"><Printer class="h-4 w-4" /> Job sheet</a>
                    <a :href="route('repairs.print', job.id) + '?format=thermal'" target="_blank" class="btn-secondary"><Receipt class="h-4 w-4" /> 80mm</a>
                    <a v-if="waLink" :href="waLink" target="_blank" class="btn-secondary !border-green-600 !text-green-700 dark:!text-green-400"><MessageCircle class="h-4 w-4" /> WhatsApp</a>
                    <Link v-if="!job.order_id" :href="route('repairs.edit', job.id)" class="btn-secondary"><Pencil class="h-4 w-4" /> Edit</Link>
                    <Link
                        v-if="!job.order_id && job.status !== 'cancelled'"
                        :href="route('orders.create', { repair_job: job.id })"
                        class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        <ShoppingCart class="h-4 w-4" /> Checkout
                    </Link>
                    <Link v-if="job.order" :href="route('orders.show', job.order.id)" class="flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-gray-900">
                        Invoice #{{ job.order.invoice }}
                    </Link>
                </div>
            </div>

            <!-- Stepper -->
            <div class="overflow-x-auto rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                <div v-if="job.status === 'cancelled'" class="text-center font-semibold text-red-600">This job was cancelled.</div>
                <ol v-else class="flex min-w-[640px] items-center">
                    <li v-for="(s, i) in flowWithParts" :key="s" class="flex flex-1 items-center">
                        <div class="flex flex-col items-center gap-1">
                            <div
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold',
                                    i < currentStep ? 'bg-emerald-500 text-white' : i === currentStep ? 'bg-indigo-600 text-white ring-4 ring-indigo-200 dark:ring-indigo-900' : 'bg-gray-200 text-gray-500 dark:bg-gray-700',
                                ]"
                            >
                                <Check v-if="i < currentStep" class="h-4 w-4" />
                                <span v-else>{{ i + 1 }}</span>
                            </div>
                            <span :class="['text-center text-xs whitespace-nowrap', i === currentStep ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500']">{{ statuses[s] }}</span>
                        </div>
                        <div v-if="i < flowWithParts.length - 1" :class="['mx-1 mb-5 h-0.5 flex-1', i < currentStep ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-700']"></div>
                    </li>
                </ol>

                <!-- Status actions -->
                <div v-if="!job.order_id && job.status !== 'cancelled'" class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:items-center dark:border-gray-700">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="next in nextActions"
                            :key="next.status"
                            type="button"
                            @click="openStatus(next.status)"
                            :class="['flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium', next.primary ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700']"
                        >
                            {{ next.label }}
                        </button>
                    </div>
                    <select @change="openStatus($event.target.value); $event.target.value = ''" class="rounded-lg border border-gray-300 py-1.5 text-sm sm:ml-auto dark:border-gray-600 dark:bg-gray-700">
                        <option value="">Set status…</option>
                        <option v-for="(label, key) in settableStatuses" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Issue & diagnosis -->
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Reported issue</div>
                        <p class="mt-1 whitespace-pre-line text-gray-900 dark:text-gray-100">{{ job.issue }}</p>
                        <div class="mt-4 flex items-center justify-between">
                            <div class="text-xs font-semibold text-gray-500 uppercase">Technician diagnosis</div>
                            <button v-if="!editingDiagnosis" type="button" class="text-xs text-indigo-600 hover:underline" @click="editingDiagnosis = true">{{ job.diagnosis ? 'Edit' : 'Add' }}</button>
                        </div>
                        <div v-if="editingDiagnosis" class="mt-1">
                            <textarea v-model="diagnosisDraft" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"></textarea>
                            <div class="mt-1 flex justify-end gap-2">
                                <button type="button" class="text-sm text-gray-500" @click="editingDiagnosis = false">Cancel</button>
                                <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-sm text-white" @click="saveDiagnosis">Save</button>
                            </div>
                        </div>
                        <p v-else class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ job.diagnosis || '—' }}</p>
                    </div>

                    <!-- Parts & labour -->
                    <div class="rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">Parts & labour</div>
                            <div class="text-sm text-gray-500">Estimate at intake: RM {{ money(job.estimated_cost) }}</div>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="item in job.items" :key="item.id">
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ item.name }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ item.item_type === 'part' ? 'Spare part' : item.item_type === 'service' ? 'Labour / service' : 'Product' }}
                                            <span v-if="item.stock !== null && item.stock !== undefined" :class="item.stock < item.quantity ? 'font-semibold text-red-600' : ''"> · {{ item.stock }} in stock</span>
                                            <span v-if="item.warranty_days"> · {{ item.warranty_days }}d warranty</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-right whitespace-nowrap text-gray-600 dark:text-gray-300">{{ item.quantity }} × {{ money(item.price) }}</td>
                                    <td class="px-2 py-2 text-right font-semibold whitespace-nowrap">RM {{ money(item.total) }}</td>
                                    <td class="w-8 pr-3">
                                        <button v-if="!job.order_id" type="button" @click="removeItem(item)" class="text-gray-400 hover:text-red-600"><Trash2 class="h-4 w-4" /></button>
                                    </td>
                                </tr>
                                <tr v-if="!job.items.length">
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No parts or labour added yet.</td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t border-gray-200 dark:border-gray-700">
                                <tr>
                                    <td class="px-4 py-2 text-right text-gray-600 dark:text-gray-300" colspan="2">{{ job.items.length ? 'Total' : 'Estimated' }}</td>
                                    <td class="px-2 py-2 text-right font-bold">RM {{ money(job.quoted_total) }}</td>
                                    <td></td>
                                </tr>
                                <tr v-if="job.deposit > 0">
                                    <td class="px-4 py-1 text-right text-green-700" colspan="2">Deposit ({{ job.deposit_method?.replace('_', ' ') }})</td>
                                    <td class="px-2 py-1 text-right text-green-700">− RM {{ money(job.deposit) }}</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 text-right font-semibold" colspan="2">Balance</td>
                                    <td class="px-2 py-2 text-right text-lg font-bold">RM {{ money(Math.max(0, job.quoted_total - job.deposit)) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Add item -->
                        <div v-if="!job.order_id" class="border-t border-gray-100 p-4 dark:border-gray-700">
                            <div class="relative">
                                <input
                                    v-model="itemSearch"
                                    @input="searchParts"
                                    @focus="showResults = true"
                                    @blur="hideResultsSoon"
                                    placeholder="Add part or service — search by name, SKU or phone model…"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                                />
                                <div v-if="showResults && itemSearch.length >= 2" class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    <button
                                        v-for="p in partResults"
                                        :key="p.id"
                                        type="button"
                                        @mousedown.prevent="pickPart(p)"
                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                    >
                                        <span>
                                            <span class="font-medium">{{ p.name }}</span>
                                            <span class="block text-xs text-gray-500">
                                                {{ p.type === 'service' ? 'Service' : `${p.type === 'part' ? 'Part' : 'Product'} · ${p.stock} in stock` }}
                                                <template v-if="p.compatible_models"> · fits {{ p.compatible_models }}</template>
                                            </span>
                                        </span>
                                        <span class="font-semibold">RM {{ money(p.price) }}</span>
                                    </button>
                                    <button type="button" @mousedown.prevent="pickCustom" class="block w-full border-t border-gray-100 px-3 py-2 text-left text-sm text-indigo-600 hover:bg-indigo-50 dark:border-gray-700 dark:hover:bg-gray-700">
                                        + Custom charge “{{ itemSearch }}”
                                    </button>
                                </div>
                            </div>
                            <div v-if="newItem.name" class="mt-2 flex flex-wrap items-end gap-2 rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40">
                                <div class="min-w-40 flex-1">
                                    <div class="text-xs text-gray-500">Item</div>
                                    <input v-model="newItem.name" :readonly="!!newItem.product_id" class="w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <div v-if="!newItem.product_id">
                                    <div class="text-xs text-gray-500">Type</div>
                                    <select v-model="newItem.item_type" class="rounded border border-gray-300 py-1 text-sm dark:border-gray-600 dark:bg-gray-700">
                                        <option value="service">Labour</option>
                                        <option value="part">Part</option>
                                    </select>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Qty</div>
                                    <input v-model.number="newItem.quantity" type="number" min="1" class="w-16 rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Price (RM)</div>
                                    <input v-model.number="newItem.price" type="number" min="0" step="0.01" class="w-24 rounded border border-gray-300 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Warranty d</div>
                                    <input v-model.number="newItem.warranty_days" type="number" min="0" class="w-16 rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700" />
                                </div>
                                <button type="button" @click="addItem" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Add</button>
                                <button type="button" @click="resetNewItem" class="px-2 py-1.5 text-sm text-gray-500">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Activity</div>
                        <div class="mb-4 flex gap-2">
                            <input
                                v-model="noteDraft"
                                @keydown.enter.prevent="addNote"
                                placeholder="Add a note…"
                                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                            />
                            <label class="flex items-center gap-1 text-xs whitespace-nowrap text-gray-600 dark:text-gray-300">
                                <input v-model="noteVisible" type="checkbox" class="rounded" /> Customer can see
                            </label>
                            <button type="button" @click="addNote" class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-gray-900">Post</button>
                        </div>
                        <ol class="relative space-y-4 border-l border-gray-200 pl-5 dark:border-gray-700">
                            <li v-for="log in job.logs" :key="log.id" class="relative">
                                <span :class="['absolute top-1.5 -left-[25px] h-2.5 w-2.5 rounded-full ring-4 ring-white dark:ring-gray-800', log.to_status ? statusStyle(log.to_status).dot : 'bg-gray-300']"></span>
                                <div class="text-sm">
                                    <span v-if="log.to_status" class="font-semibold text-gray-900 dark:text-gray-100">{{ statuses[log.to_status] || log.to_status }}</span>
                                    <span v-if="log.to_status && log.note"> — </span>
                                    <span class="text-gray-700 dark:text-gray-300">{{ log.note }}</span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ log.user || 'System' }} · <span :title="log.created_at">{{ log.ago }}</span>
                                    <span v-if="log.customer_visible" class="ml-1 rounded bg-blue-50 px-1 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">visible to customer</span>
                                </div>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-4">
                    <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Customer</div>
                        <template v-if="job.customer">
                            <Link :href="route('customers.edit', job.customer.id)" class="mt-1 block font-semibold text-gray-900 hover:underline dark:text-gray-100">{{ job.customer.name }}</Link>
                            <div class="text-sm text-gray-600 dark:text-gray-300">{{ job.customer.phone }}</div>
                            <div v-if="job.customer.email" class="text-sm text-gray-600 dark:text-gray-300">{{ job.customer.email }}</div>
                            <div class="mt-2 flex gap-2">
                                <a v-if="job.customer.phone" :href="`tel:${job.customer.phone}`" class="btn-secondary !py-1 text-xs"><Phone class="h-3.5 w-3.5" /> Call</a>
                                <a v-if="waLink" :href="waLink" target="_blank" class="btn-secondary !py-1 text-xs"><MessageCircle class="h-3.5 w-3.5" /> WhatsApp update</a>
                            </div>
                        </template>
                        <div v-else class="mt-1 text-sm text-gray-500">No customer</div>
                    </div>

                    <div class="space-y-2 rounded-xl bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Job details</div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-gray-500">Technician</span>
                            <select :value="job.technician_id || ''" @change="quick({ technician_id: $event.target.value || null })" :disabled="!!job.order_id" class="rounded border border-gray-200 py-0.5 text-sm dark:border-gray-600 dark:bg-gray-700">
                                <option value="">Unassigned</option>
                                <option v-for="t in technicians" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div class="flex justify-between"><span class="text-gray-500">Received</span><span>{{ job.created_at }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Received by</span><span>{{ job.received_by || '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Promised</span><span :class="isOverdue ? 'font-semibold text-red-600' : ''">{{ job.promised_at ? job.promised_at.replace('T', ' ') : '—' }}</span></div>
                        <div v-if="job.approved_at" class="flex justify-between"><span class="text-gray-500">Approved</span><span>{{ job.approved_at }}</span></div>
                        <div v-if="job.completed_at" class="flex justify-between"><span class="text-gray-500">Completed</span><span>{{ job.completed_at }}</span></div>
                        <div v-if="job.collected_at" class="flex justify-between"><span class="text-gray-500">Collected</span><span>{{ job.collected_at }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Warranty</span><span>{{ job.warranty_days }} days</span></div>
                    </div>

                    <div class="space-y-2 rounded-xl bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
                        <div class="text-xs font-semibold text-gray-500 uppercase">Device intake</div>
                        <div class="flex items-center justify-between">
                            <span class="text-gray-500">Screen lock</span>
                            <span v-if="job.passcode_type === 'none'">None</span>
                            <button v-else type="button" class="font-mono text-indigo-600" @click="showPasscode = !showPasscode">
                                {{ showPasscode ? `${job.passcode_type}: ${job.passcode || '—'}` : `Show ${job.passcode_type}` }}
                            </button>
                        </div>
                        <div>
                            <span class="text-gray-500">Accessories:</span>
                            {{ job.accessories.length ? job.accessories.join(', ') : 'None' }}
                        </div>
                        <div v-if="Object.keys(job.pre_checks || {}).length" class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
                            <div v-for="(v, k) in job.pre_checks" :key="k" class="flex items-center gap-1">
                                <span :class="v === 'ok' ? 'text-emerald-600' : v === 'faulty' ? 'text-red-600' : 'text-gray-400'">{{ v === 'ok' ? '✔' : v === 'faulty' ? '✘' : '–' }}</span>
                                {{ preCheckLabel(k) }}
                            </div>
                        </div>
                        <div v-if="job.condition_notes" class="text-gray-700 dark:text-gray-300"><span class="text-gray-500">Condition:</span> {{ job.condition_notes }}</div>
                        <div v-if="job.internal_notes" class="rounded bg-yellow-50 p-2 text-xs text-yellow-900 dark:bg-yellow-900/30 dark:text-yellow-100">
                            <b>Internal:</b> {{ job.internal_notes }}
                        </div>
                    </div>

                    <div v-if="history.length" class="rounded-xl bg-white p-4 text-sm shadow-sm dark:bg-gray-800">
                        <div class="mb-1 text-xs font-semibold text-gray-500 uppercase">Previous repairs on this IMEI</div>
                        <Link v-for="h in history" :key="h.id" :href="route('repairs.show', h.id)" class="block rounded px-1 py-1 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <span class="font-mono">{{ h.job_number }}</span> · {{ h.created_at }}
                            <span v-if="h.under_warranty" class="ml-1 rounded bg-emerald-100 px-1 text-[10px] font-bold text-emerald-700">WARRANTY</span>
                            <div class="truncate text-xs text-gray-500">{{ h.issue }}</div>
                        </Link>
                    </div>

                    <button v-if="!job.order_id && canDelete" type="button" @click="destroy" class="w-full text-center text-xs text-red-600 hover:underline">Delete job</button>
                </div>
            </div>
        </div>

        <!-- Status dialog -->
        <div v-if="statusDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="statusDialog = null">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-800">
                <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">Move to “{{ statuses[statusDialog.status] }}”</div>
                <textarea
                    v-model="statusDialog.note"
                    rows="3"
                    :placeholder="statusPlaceholder(statusDialog.status)"
                    class="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700"
                ></textarea>
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input v-model="statusDialog.customer_visible" type="checkbox" class="rounded" /> Show note on customer tracking page
                </label>
                <label v-if="job.customer?.phone" class="mt-1 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input v-model="statusDialog.whatsapp" type="checkbox" class="rounded" /> Open WhatsApp to notify customer
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="statusDialog = null" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Cancel</button>
                    <button type="button" @click="submitStatus" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Update</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { deviceIcon, FLOW, statusStyle, whatsappTemplate } from '@/Components/service/repairStyles';
import { money, useFlashToast, whatsappUrl } from '@/composables/useFlashToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import debounce from 'lodash/debounce';
import { Check, MessageCircle, Pencil, Phone, Printer, Receipt, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    job: Object,
    history: Array,
    statuses: Object,
    technicians: Array,
    shop: Object,
    trackUrl: String,
});

useFlashToast();
const page = usePage();
const roles = page.props.auth?.roles || [];
const canDelete = roles.includes('admin') || roles.includes('manager');

const PRE_CHECK_LABELS = {
    power: 'Powers on', display: 'Display', touch: 'Touch', front_camera: 'Front cam', rear_camera: 'Rear cam', speaker: 'Speaker',
    microphone: 'Mic', charging: 'Charging', buttons: 'Buttons', wifi: 'Wi-Fi/BT', signal: 'Signal', biometrics: 'Face ID/FP',
};
const preCheckLabel = (k) => PRE_CHECK_LABELS[k] || k;

// ---- stepper ----
const flowWithParts = computed(() => (props.job.status === 'awaiting_parts' ? [...FLOW.slice(0, 3), 'awaiting_parts', ...FLOW.slice(3)] : FLOW));
const currentStep = computed(() => {
    const i = flowWithParts.value.indexOf(props.job.status);
    return i === -1 ? 0 : i;
});
const NEXT = {
    received: [{ status: 'diagnosing', label: 'Start diagnosis', primary: true }, { status: 'in_progress', label: 'Start repair' }],
    diagnosing: [{ status: 'awaiting_approval', label: 'Send quote to customer', primary: true }, { status: 'in_progress', label: 'Start repair' }],
    awaiting_approval: [{ status: 'in_progress', label: 'Customer approved – start repair', primary: true }, { status: 'awaiting_parts', label: 'Approved – order parts' }, { status: 'cancelled', label: 'Customer declined' }],
    awaiting_parts: [{ status: 'in_progress', label: 'Parts arrived – start repair', primary: true }],
    in_progress: [{ status: 'ready', label: 'Repair done – ready for pickup', primary: true }, { status: 'awaiting_parts', label: 'Waiting for parts' }],
    ready: [{ status: 'in_progress', label: 'Reopen repair' }],
};
const nextActions = computed(() => NEXT[props.job.status] || []);
const settableStatuses = computed(() => Object.fromEntries(Object.entries(props.statuses).filter(([k]) => k !== 'collected' && k !== props.job.status)));
const isOverdue = computed(() => props.job.promised_at && new Date(props.job.promised_at) < new Date() && !['ready', 'collected', 'cancelled'].includes(props.job.status));

const waLink = computed(() => (props.job.customer?.phone ? whatsappUrl(props.job.customer.phone, whatsappTemplate(props.job, props.shop, props.trackUrl)) : null));

// ---- status dialog ----
const statusDialog = ref(null);
const openStatus = (status) => {
    if (!status) return;
    statusDialog.value = { status, note: '', customer_visible: true, whatsapp: ['awaiting_approval', 'ready'].includes(status) && !!props.job.customer?.phone };
};
const statusPlaceholder = (s) =>
    ({
        awaiting_approval: 'Quote details for the customer, e.g. "Screen + labour RM 350"',
        awaiting_parts: 'Which part, supplier, ETA…',
        ready: 'What was done',
        cancelled: 'Reason (e.g. customer declined, not repairable)',
    })[s] || 'Optional note';
const submitStatus = () => {
    const d = statusDialog.value;
    // Open the WhatsApp tab synchronously so pop-up blockers allow it; its
    // message reflects the new status.
    if (d.whatsapp && props.job.customer?.phone) {
        const preview = { ...props.job, status: d.status, diagnosis: props.job.diagnosis || d.note };
        window.open(whatsappUrl(props.job.customer.phone, whatsappTemplate(preview, props.shop, props.trackUrl)), '_blank');
    }
    router.post(route('repairs.status', props.job.id), { status: d.status, note: d.note, customer_visible: d.customer_visible }, { preserveScroll: true, onSuccess: () => (statusDialog.value = null) });
};

// ---- diagnosis / quick edits ----
const editingDiagnosis = ref(false);
const diagnosisDraft = ref(props.job.diagnosis || '');
const quick = (data) => router.patch(route('repairs.patch', props.job.id), data, { preserveScroll: true });
const saveDiagnosis = () => {
    quick({ diagnosis: diagnosisDraft.value });
    editingDiagnosis.value = false;
};

// ---- notes ----
const noteDraft = ref('');
const noteVisible = ref(false);
const addNote = () => {
    if (!noteDraft.value.trim()) return;
    router.post(route('repairs.notes', props.job.id), { note: noteDraft.value, customer_visible: noteVisible.value }, {
        preserveScroll: true,
        onSuccess: () => {
            noteDraft.value = '';
            noteVisible.value = false;
        },
    });
};

// ---- items ----
const itemSearch = ref('');
const partResults = ref([]);
const showResults = ref(false);
const hideResultsSoon = () => setTimeout(() => (showResults.value = false), 150);
const newItem = reactive({ product_id: null, name: '', item_type: 'service', quantity: 1, price: 0, warranty_days: props.job.warranty_days });
const searchParts = debounce(async () => {
    if (itemSearch.value.length < 2) return;
    const { data } = await axios.get(route('api.products.search'), { params: { q: itemSearch.value, types: 'part,service,product' } });
    partResults.value = data.filter((p) => !p.serial_tracked);
}, 250);
const pickPart = (p) => {
    Object.assign(newItem, { product_id: p.id, name: p.name, item_type: p.type, quantity: 1, price: Number(p.price), warranty_days: p.warranty_days ?? props.job.warranty_days });
    itemSearch.value = '';
    showResults.value = false;
};
const pickCustom = () => {
    Object.assign(newItem, { product_id: null, name: itemSearch.value, item_type: 'service', quantity: 1, price: 0, warranty_days: props.job.warranty_days });
    itemSearch.value = '';
    showResults.value = false;
};
const resetNewItem = () => Object.assign(newItem, { product_id: null, name: '', item_type: 'service', quantity: 1, price: 0, warranty_days: props.job.warranty_days });
const addItem = () => {
    router.post(route('repairs.items.store', props.job.id), { ...newItem }, { preserveScroll: true, onSuccess: resetNewItem });
};
const removeItem = (item) => {
    router.delete(route('repairs.items.destroy', [props.job.id, item.id]), { preserveScroll: true });
};

const showPasscode = ref(false);

const destroy = () => {
    if (confirm(`Delete repair job ${props.job.job_number}? This cannot be undone.`)) {
        router.delete(route('repairs.destroy', props.job.id));
    }
};
</script>

<style scoped>
@reference "../../../css/app.css";
.btn-secondary {
    @apply flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700;
}
</style>
