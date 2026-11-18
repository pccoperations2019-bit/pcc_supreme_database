import assert from 'node:assert/strict'
import test from 'node:test'
import {
  buildInitialFormState,
  buildFarmerFormData,
} from '../../resources/js/composable/useFarmerForm.js'

for (const [description, stored, expected] of [
  ['legacy comma-separated categories', ' cbed, pl480, cbed, ', ['cbed', 'pl480']],
  ['array categories', ['cbed', ' pl480 ', 'cbed', null, ''], ['cbed', 'pl480']],
  ['no categories', null, []],
]) {
  test(`farmer form loads ${description}`, () => {
    const form = buildInitialFormState({ project_category: stored }, 7)

    assert.deepEqual(form.project_category, expected)
  })
}

test('editing a farmer submits each selected project category once', () => {
  const form = buildInitialFormState({ project_category: 'cbed' }, 7)
  form.project_category = [' cbed ', 'pl480', 'cbed', '']

  const payload = buildFarmerFormData(form, { method: 'PUT' })

  assert.equal(payload.get('_method'), 'PUT')
  assert.equal(payload.get('project_category[0]'), 'cbed')
  assert.equal(payload.get('project_category[1]'), 'pl480')
  assert.equal(payload.has('project_category[2]'), false)
  assert.equal(payload.has('project_category'), false)
})

test('clearing all project categories submits an explicit empty value', () => {
  const form = buildInitialFormState({ project_category: ['cbed'] }, 7)
  form.project_category = []

  const payload = buildFarmerFormData(form, { method: 'PUT' })

  assert.equal(payload.has('project_category'), true)
  assert.equal(payload.get('project_category'), '')
  assert.equal(payload.has('project_category[0]'), false)
})
