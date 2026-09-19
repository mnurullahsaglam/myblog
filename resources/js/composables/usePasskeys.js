import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * WebAuthn ceremonies against laravel/passkeys.
 *
 * Uses the browser's native credential JSON API (Safari 17.4+, Chrome 119+) so
 * no WebAuthn helper library is needed. Options come from the server as JSON,
 * the browser turns them into buffers, and the credential goes back as JSON.
 *
 * WebAuthn only runs in a secure context, so the site must be served over
 * HTTPS. On plain HTTP `navigator.credentials` is undefined.
 */
export function usePasskeys() {
  const busy = ref(false)
  const error = ref(null)

  function isSupported() {
    return (
      typeof window !== 'undefined' &&
      window.isSecureContext &&
      typeof window.PublicKeyCredential !== 'undefined' &&
      typeof window.PublicKeyCredential.parseCreationOptionsFromJSON === 'function'
    )
  }

  function unsupportedReason() {
    if (typeof window === 'undefined') {
      return null
    }

    if (!window.isSecureContext) {
      return 'Passkeys need a secure connection. Serve the site over HTTPS.'
    }

    if (typeof window.PublicKeyCredential === 'undefined') {
      return 'This browser does not support passkeys.'
    }

    return 'This browser is too old for passkeys. Update it to sign in with Touch ID.'
  }

  /** Raised when the server wants the password re-confirmed first. */
  class PasswordConfirmationRequired extends Error {}

  async function fetchOptions(url) {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    // Managing passkeys sits behind Fortify's password confirmation.
    if (response.status === 423) {
      throw new PasswordConfirmationRequired()
    }

    if (!response.ok) {
      throw new Error('Could not start the passkey ceremony.')
    }

    const { options } = await response.json()

    return options
  }

  /** Translate the browser's errors into something worth reading. */
  function describe(exception) {
    if (exception?.name === 'NotAllowedError') {
      return 'Cancelled, or the request timed out.'
    }

    if (exception?.name === 'InvalidStateError') {
      return 'This device already has a passkey for this account.'
    }

    if (exception?.name === 'SecurityError') {
      return 'The site origin does not match the passkey. Check that you are on the HTTPS URL.'
    }

    return exception?.message ?? 'Something went wrong.'
  }

  async function register(name) {
    error.value = null

    if (!isSupported()) {
      error.value = unsupportedReason()

      return false
    }

    busy.value = true

    try {
      const options = await fetchOptions(route('passkey.registration-options'))

      const credential = await navigator.credentials.create({
        publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options),
      })

      if (!credential) {
        throw new Error('No credential was created.')
      }

      await new Promise((resolve, reject) => {
        router.post(
          route('passkey.store'),
          { name, credential: credential.toJSON() },
          {
            preserveScroll: true,
            onSuccess: resolve,
            onError: (errors) => reject(new Error(Object.values(errors)[0] ?? 'Rejected.')),
            onFinish: () => (busy.value = false),
          },
        )
      })

      return true
    } catch (exception) {
      if (exception instanceof PasswordConfirmationRequired) {
        router.visit(route('password.confirm'))

        return false
      }

      error.value = describe(exception)
      busy.value = false

      return false
    }
  }

  async function login(remember = false) {
    error.value = null

    if (!isSupported()) {
      error.value = unsupportedReason()

      return false
    }

    busy.value = true

    try {
      const options = await fetchOptions(route('passkey.login-options'))

      const credential = await navigator.credentials.get({
        publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options),
      })

      if (!credential) {
        throw new Error('No credential was returned.')
      }

      router.post(
        route('passkey.login'),
        { credential: credential.toJSON(), remember },
        {
          onError: (errors) => {
            error.value = Object.values(errors)[0] ?? 'That passkey was not accepted.'
          },
          onFinish: () => (busy.value = false),
        },
      )

      return true
    } catch (exception) {
      error.value = describe(exception)
      busy.value = false

      return false
    }
  }

  return { busy, error, isSupported, unsupportedReason, register, login }
}
