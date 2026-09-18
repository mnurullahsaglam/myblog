import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Keeps sort, search, filters and pagination in the URL, so the back button,
 * bookmarks and shared links all work. Reloads ask only for `rows`, which the
 * controller exposes as a closure, so nothing else is recomputed.
 */
export function useTableState(schema, resource) {
    const params = new URLSearchParams(window.location.search)

    const state = reactive({
        sort: params.get('sort') ?? schema.defaultSort,
        search: params.get('search') ?? '',
        perPage: Number(params.get('perPage')) || schema.perPage,
        page: Number(params.get('page')) || 1,
        filters: readFilters(params, schema.filters),
    })

    let searchTimer = null
    let hydrating = true

    function readFilters(searchParams, filterSchema) {
        const filters = {}

        filterSchema.forEach((filter) => {
            if (filter.type === 'dateRange') {
                const from = searchParams.get(`filter[${filter.key}][from]`)
                const to = searchParams.get(`filter[${filter.key}][to]`)
                filters[filter.key] = from || to ? { from, to } : null

                return
            }

            if (filter.multiple) {
                const values = searchParams.getAll(`filter[${filter.key}][]`)
                filters[filter.key] = values.length ? values : []

                return
            }

            filters[filter.key] = searchParams.get(`filter[${filter.key}]`) ?? filter.default ?? null
        })

        return filters
    }

    function pruneFilters(filters) {
        const pruned = {}

        Object.entries(filters).forEach(([key, value]) => {
            if (value === null || value === '' || (Array.isArray(value) && value.length === 0)) {
                return
            }

            if (typeof value === 'object' && !Array.isArray(value)) {
                const entries = Object.entries(value).filter(([, item]) => item)

                if (entries.length) {
                    pruned[key] = Object.fromEntries(entries)
                }

                return
            }

            pruned[key] = value
        })

        return pruned
    }

    function reload({ resetPage = false } = {}) {
        if (resetPage) {
            state.page = 1
        }

        router.get(
            route(`admin.${resource}.index`),
            {
                sort: state.sort,
                search: state.search || undefined,
                perPage: state.perPage,
                page: state.page,
                filter: pruneFilters(state.filters),
            },
            {
                only: ['rows'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        )
    }

    watch(
        () => state.search,
        () => {
            if (hydrating) {
                return
            }

            clearTimeout(searchTimer)
            searchTimer = setTimeout(() => reload({ resetPage: true }), 300)
        },
    )

    watch(
        () => state.filters,
        () => {
            if (!hydrating) {
                reload({ resetPage: true })
            }
        },
        { deep: true },
    )

    // Let the initial values settle before watchers start firing reloads.
    queueMicrotask(() => {
        hydrating = false
    })

    return { state, reload }
}
