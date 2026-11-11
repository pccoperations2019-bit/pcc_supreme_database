/**
 * Splitting and rebuilding the `client_name` column of a service record.
 *
 * The column is one string, but it is written in two shapes: "Last, First Middle"
 * by the imports and by this form, and "First Middle Last" by hand-entered rows
 * that never went through a comma. Both shapes appear in the table, so a name is
 * read the way `ServiceRecordImport::parseClientName()` reads it - comma means the
 * last name comes first, no comma means it comes last.
 *
 * A generational suffix ("Jr.", "Sr.", "II") is kept in its own field rather than
 * glued to the last name, because it is neither: it is dropped from whichever
 * side of the name it was written on.
 */

/** The suffixes a name may end with, in the form they are stored. */
export const CLIENT_NAME_SUFFIXES = ['Jr.', 'Sr.', 'II', 'III', 'IV']

const matchesSuffix = token =>
  CLIENT_NAME_SUFFIXES.some(
    suffix => suffix.replace('.', '').toLowerCase() === String(token).replace('.', '').toLowerCase()
  )

const canonicalSuffix = token =>
  CLIENT_NAME_SUFFIXES.find(
    suffix => suffix.replace('.', '').toLowerCase() === String(token).replace('.', '').toLowerCase()
  ) ?? ''

/**
 * The parts of a stored client name. Every part is a string, empty when the name
 * does not carry it.
 *
 * @param {string|null|undefined} value
 * @returns {{firstName: string, middleName: string, lastName: string, suffix: string}}
 */
export function parseClientName(value) {
  const blank = { firstName: '', middleName: '', lastName: '', suffix: '' }
  const trimmed = String(value ?? '').trim()

  if (trimmed === '') return blank

  let lastName = ''
  let given = trimmed

  if (trimmed.includes(',')) {
    // The remainder is rejoined rather than dropped: a stray second comma
    // ("Cariaga, Jenifer, D") splits the name further, it does not end it.
    const [before, ...rest] = trimmed.split(',').map(part => part.trim())
    const after = rest.join(' ').trim()

    // "Antonio Borja,Jr" writes the suffix where the last name belongs, so the
    // name itself is still in "First Middle Last" order.
    if (matchesSuffix(before) && after !== '') {
      given = `${after} ${before}`.trim()
    } else {
      lastName = before
      given = after
    }
  }

  // "Santos, Jr." is the same thing the other way round: the suffix is the whole
  // of one side of the comma, so the name it displaced is the last name.
  if (lastName !== '' && matchesSuffix(given)) {
    given = `${lastName} ${given}`.trim()
    lastName = ''
  }

  let tokens = given.split(/\s+/).filter(Boolean)

  const tail = tokens[tokens.length - 1] ?? ''
  if (tokens.length > 1 && matchesSuffix(tail)) {
    tokens = tokens.slice(0, -1)
    return { ...blank, ...splitTokens(tokens, lastName), suffix: canonicalSuffix(tail) }
  }

  return { ...blank, ...splitTokens(tokens, lastName) }
}

/**
 * First name, middle name and last name out of the remaining tokens.
 *
 * @param {string[]} tokens
 * @param {string} lastName The last name already read from a comma, if any.
 */
function splitTokens(tokens, lastName) {
  if (lastName !== '') {
    return {
      firstName: tokens[0] ?? '',
      middleName: tokens.slice(1).join(' '),
      lastName,
    }
  }

  return {
    firstName: tokens[0] ?? '',
    middleName: tokens.slice(1, -1).join(' '),
    lastName: tokens.length > 1 ? tokens[tokens.length - 1] : '',
  }
}

/**
 * The stored form of a name, back to "Last, First Middle Suffix".
 *
 * @param {{firstName?: string, middleName?: string, lastName?: string, suffix?: string}} parts
 * @returns {string}
 */
export function formatClientName({ firstName, middleName, lastName, suffix }) {
  const last = (lastName ?? '').trim()
  const given = [(firstName ?? '').trim(), (middleName ?? '').trim()].filter(Boolean).join(' ')
  const withSuffix = [given, (suffix ?? '').trim()].filter(Boolean).join(' ')

  return [last, withSuffix].filter(Boolean).join(', ')
}
