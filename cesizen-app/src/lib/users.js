export function normalizeUsers(payload) {
  if (Array.isArray(payload)) {
    return payload
  }

  if (Array.isArray(payload?.['hydra:member'])) {
    return payload['hydra:member']
  }

  if (Array.isArray(payload?.member)) {
    return payload.member
  }

  return []
}
