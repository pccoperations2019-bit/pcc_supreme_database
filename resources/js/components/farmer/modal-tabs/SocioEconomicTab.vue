<script setup>
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const form = defineModel('form', { type: Object, required: true })

const { enums } = defineProps({
  enums: { type: Object, default: () => ({}) },
})

const positionOptions = [
  'Member',
  'President',
  'Vice President',
  'Secretary',
  'Treasurer',
  'Auditor',
]
</script>

<template>
  <div class="col-span-1 space-y-6 md:col-span-2">
    <div class="space-y-4 rounded-lg border p-4">
      <div class="space-y-6">
        <div>
          <h5 class="text-muted-foreground mb-3 text-sm font-semibold uppercase">
            Household and Family
          </h5>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Family Member Attending School Count</Label>
              <Input
                v-model.number="form.family_member_attending_school_count"
                type="number"
                min="0"
              />
            </div>
            <div class="space-y-2">
              <Label>Female Count</Label>
              <Input v-model.number="form.female_count" type="number" min="0" />
            </div>
            <div class="space-y-2">
              <Label>Male Count</Label>
              <Input v-model.number="form.male_count" type="number" min="0" />
            </div>
            <div class="space-y-2">
              <Label>Are You the Head of the Family</Label>
              <Select v-model="form.are_you_the_head_of_the_family">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="col-span-1 space-y-2 md:col-span-2">
              <Label>If No, Highest Educational Attainment of Head of the Family</Label>
              <Input v-model="form.if_no_highest_educational_attainment_of_head_of_family" />
            </div>
          </div>
        </div>

        <div>
          <h5 class="text-muted-foreground mb-3 text-sm font-semibold uppercase">
            Socio-Economic Profile
          </h5>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <Label>Position</Label>
              <Select v-model="form.position">
                <SelectTrigger><SelectValue placeholder="Select position" /></SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="option in positionOptions" :key="option" :value="option">
                    {{ option }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Year Registered as a Member</Label>
              <Input v-model="form.year_involvement_in_business" type="number" min="0" />
            </div>
            <div class="space-y-2">
              <Label>PWD Member</Label>
              <Select v-model="form.is_pwd">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>4Ps Member</Label>
              <Select v-model="form.is_4ps">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>IPS Member</Label>
              <Select v-model="form.is_ips">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Indigent Person</Label>
              <Select v-model="form.is_indigent">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Indigenous Group</Label>
              <Input
                v-model="form.indigenous_group"
                :disabled="!form.is_indigent"
                placeholder="Specify the indigenous group"
              />
              <p v-if="form.is_indigent" class="text-muted-foreground text-xs">
                If yes, specify the indigenous group
              </p>
            </div>
            <div class="space-y-2">
              <Label>Educational Attainment</Label>
              <Select v-model="form.educational_attainment">
                <SelectTrigger
                  ><SelectValue placeholder="Select educational attainment"
                /></SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="(label, key) in enums.educationalAttainment"
                    :key="key"
                    :value="key"
                  >
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Involved in Carabao Farming</Label>
              <Select v-model="form.involved_in_buffalo_farming">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem :value="true">Yes</SelectItem>
                  <SelectItem :value="false">No</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>How Many in Family Involved in Carabao Farming</Label>
              <Input
                v-model.number="form.how_many_in_family_are_involved_in_buffalo_farming"
                type="number"
                min="0"
              />
            </div>
            <div class="space-y-2">
              <Label>Membership in Organization</Label>
              <Select v-model="form.membership_in_organization">
                <SelectTrigger><SelectValue placeholder="Select membership type" /></SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="(labelValue, key) in enums.membershipType"
                    :key="key"
                    :value="key"
                  >
                    {{ labelValue }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div class="space-y-2">
              <Label>Name of Organization</Label>
              <Input v-model="form.name_of_organization" placeholder="Enter organization name" />
            </div>
            <div class="space-y-2">
              <Label>Type of Entrepreneur</Label>
              <Select v-model="form.type_of_entrepreneur">
                <SelectTrigger><SelectValue placeholder="Select type" /></SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="(label, key) in enums.entrepreneurType"
                    :key="key"
                    :value="key"
                  >
                    {{ label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
