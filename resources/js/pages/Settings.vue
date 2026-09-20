<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import InputText from 'primevue/inputtext'
import SelectButton from 'primevue/selectbutton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Textarea from 'primevue/textarea'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AccentPicker from '@/Components/Settings/AccentPicker.vue'

const props = defineProps({
  settings: { type: Object, required: true },
  accents: { type: Array, required: true },
  schemes: { type: Array, required: true },
})

const form = useForm({
  appearance: {
    accent: props.settings.appearance?.accent ?? 'khaki',
    color_scheme: props.settings.appearance?.color_scheme ?? 'system',
  },
  site_info: { ...props.settings.site_info },
  meta: { ...props.settings.meta, meta_keywords: props.settings.meta?.meta_keywords ?? [] },
  branding: { ...props.settings.branding },
  social: { ...props.settings.social },
  contact: { ...props.settings.contact },
})

watch(
  () => form.appearance.color_scheme,
  (scheme) => {
    const dark = scheme === 'dark' || (scheme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)

    document.documentElement.classList.toggle('dark', dark)
  },
)

function submit() {
  form
    .transform((data) => ({ ...data, _method: 'put' }))
    .post(route('admin.settings.update'), { forceFormData: true, preserveScroll: true })
}

const labelClass = 'font-mono text-[11px] font-semibold uppercase tracking-[0.06em] text-surface-500'

const TEXT_FIELDS = {
  site_info: [
    ['site_name', 'Site name'],
    ['site_url', 'Site URL'],
    ['admin_email', 'Admin email'],
  ],
  social: [
    ['github_url', 'GitHub'],
    ['twitter_url', 'Twitter'],
    ['linkedin_url', 'LinkedIn'],
    ['instagram_url', 'Instagram'],
    ['facebook_url', 'Facebook'],
    ['youtube_url', 'YouTube'],
  ],
  contact: [
    ['phone', 'Phone'],
    ['city', 'City'],
    ['postal_code', 'Postal code'],
    ['country', 'Country'],
  ],
}

const UPLOADS = [
  ['branding', 'logo', 'Logo'],
  ['branding', 'footer_logo', 'Footer logo'],
  ['branding', 'favicon', 'Favicon'],
]
</script>

<template>
  <AdminLayout title="Settings">
    <template #actions>
      <Button label="Save settings" icon="pi pi-check" :loading="form.processing" @click="submit" />
    </template>

    <form class="max-w-3xl" @submit.prevent="submit">
      <Tabs value="appearance">
        <TabList>
          <Tab value="appearance">Appearance</Tab>
          <Tab value="site">Site</Tab>
          <Tab value="meta">Meta</Tab>
          <Tab value="branding">Branding</Tab>
          <Tab value="social">Social</Tab>
          <Tab value="contact">Contact</Tab>
        </TabList>

        <TabPanels>
          <TabPanel value="appearance">
            <div class="flex flex-col gap-6 pt-2">
              <div class="flex flex-col gap-2">
                <span :class="labelClass" lang="en">Accent</span>
                <AccentPicker v-model="form.appearance.accent" :accents="accents" />
                <small class="text-surface-500">
                  Used for primary actions and active states. Filled accents always take dark text.
                </small>
              </div>

              <div class="flex flex-col gap-2">
                <span :class="labelClass" lang="en">Colour scheme</span>
                <SelectButton
                  v-model="form.appearance.color_scheme"
                  :options="schemes"
                  option-label="label"
                  option-value="value"
                  :allow-empty="false"
                />
                <small class="text-surface-500">System follows your operating system.</small>
              </div>
            </div>
          </TabPanel>

          <TabPanel value="site">
            <div class="flex flex-col gap-4 pt-2">
              <div v-for="[key, label] in TEXT_FIELDS.site_info" :key="key" class="flex flex-col gap-1.5">
                <label :for="key" :class="labelClass" lang="en">{{ label }}</label>
                <InputText
                  :id="key"
                  v-model="form.site_info[key]"
                  fluid
                  :invalid="Boolean(form.errors[`site_info.${key}`])"
                />
                <small v-if="form.errors[`site_info.${key}`]" class="text-[#D95757]">
                  {{ form.errors[`site_info.${key}`] }}
                </small>
              </div>

              <div class="flex flex-col gap-1.5">
                <label for="site_description" :class="labelClass" lang="en">Site description</label>
                <Textarea id="site_description" v-model="form.site_info.site_description" :rows="3" />
              </div>
            </div>
          </TabPanel>

          <TabPanel value="meta">
            <div class="flex flex-col gap-4 pt-2">
              <div class="flex flex-col gap-1.5">
                <label for="meta_title" :class="labelClass" lang="en">Meta title</label>
                <InputText id="meta_title" v-model="form.meta.meta_title" fluid />
              </div>

              <div class="flex flex-col gap-1.5">
                <label for="meta_description" :class="labelClass" lang="en">Meta description</label>
                <Textarea id="meta_description" v-model="form.meta.meta_description" :rows="3" />
                <small class="text-surface-500">160 characters at most.</small>
              </div>

              <div class="flex flex-col gap-1.5">
                <label :class="labelClass" lang="en">Keywords</label>
                <AutoComplete
                  v-model="form.meta.meta_keywords"
                  multiple
                  :typeahead="false"
                  fluid
                  placeholder="Type and press enter"
                />
              </div>

              <div class="flex flex-col gap-1.5">
                <label for="og_title" :class="labelClass" lang="en">Open Graph title</label>
                <InputText id="og_title" v-model="form.meta.og_title" fluid />
              </div>

              <div class="flex flex-col gap-1.5">
                <label for="og_description" :class="labelClass" lang="en">Open Graph description</label>
                <Textarea id="og_description" v-model="form.meta.og_description" :rows="3" />
              </div>

              <div class="flex flex-col gap-1.5">
                <label :class="labelClass" lang="en">Open Graph image</label>
                <img
                  v-if="typeof form.meta.og_image === 'string' && form.meta.og_image"
                  :src="`/storage/${form.meta.og_image}`"
                  alt=""
                  class="border-surface-200 h-24 w-auto rounded border object-cover dark:border-[#272B35]"
                />
                <FileUpload
                  mode="basic"
                  accept="image/*"
                  :max-file-size="5242880"
                  choose-label="Choose image"
                  custom-upload
                  auto
                  @uploader="form.meta.og_image = $event.files[0]"
                />
              </div>
            </div>
          </TabPanel>

          <TabPanel value="branding">
            <div class="flex flex-col gap-5 pt-2">
              <div v-for="[group, key, label] in UPLOADS" :key="key" class="flex flex-col gap-1.5">
                <label :class="labelClass" lang="en">{{ label }}</label>
                <img
                  v-if="typeof form[group][key] === 'string' && form[group][key]"
                  :src="`/storage/${form[group][key]}`"
                  alt=""
                  class="border-surface-200 h-16 w-auto rounded border object-contain dark:border-[#272B35]"
                />
                <FileUpload
                  mode="basic"
                  accept="image/*"
                  :max-file-size="5242880"
                  choose-label="Choose image"
                  custom-upload
                  auto
                  @uploader="form[group][key] = $event.files[0]"
                />
              </div>

              <p class="border-surface-200 text-surface-500 rounded border p-3 text-[13px] dark:border-[#272B35]">
                These colours style the public site. The admin accent is set on the Appearance tab.
              </p>

              <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                  <label for="primary_color" :class="labelClass" lang="en">Primary colour</label>
                  <InputText id="primary_color" v-model="form.branding.primary_color" fluid />
                </div>
                <div class="flex flex-col gap-1.5">
                  <label for="secondary_color" :class="labelClass" lang="en">Secondary colour</label>
                  <InputText id="secondary_color" v-model="form.branding.secondary_color" fluid />
                </div>
              </div>
            </div>
          </TabPanel>

          <TabPanel value="social">
            <div class="flex flex-col gap-4 pt-2">
              <div v-for="[key, label] in TEXT_FIELDS.social" :key="key" class="flex flex-col gap-1.5">
                <label :for="key" :class="labelClass" lang="en">{{ label }}</label>
                <InputText
                  :id="key"
                  v-model="form.social[key]"
                  fluid
                  placeholder="https://"
                  :invalid="Boolean(form.errors[`social.${key}`])"
                />
                <small v-if="form.errors[`social.${key}`]" class="text-[#D95757]">
                  {{ form.errors[`social.${key}`] }}
                </small>
              </div>
            </div>
          </TabPanel>

          <TabPanel value="contact">
            <div class="flex flex-col gap-4 pt-2">
              <div class="flex flex-col gap-1.5">
                <label for="address" :class="labelClass" lang="en">Address</label>
                <Textarea id="address" v-model="form.contact.address" :rows="2" />
              </div>

              <div class="grid gap-4 sm:grid-cols-2">
                <div v-for="[key, label] in TEXT_FIELDS.contact" :key="key" class="flex flex-col gap-1.5">
                  <label :for="key" :class="labelClass" lang="en">{{ label }}</label>
                  <InputText :id="key" v-model="form.contact[key]" fluid />
                </div>
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </form>
  </AdminLayout>
</template>
