<template>
  Use datay
  <div style="width:min-content; margin:auto">
    <datepicker v-model="picked" startingView="month"	/>
  </div>
  {{picked}}
</template>

<script>
import Datepicker from 'vue3-datepicker'
import { ref } from 'vue'

export default {
  components: {
    Datepicker
  },
  name: 'DatePicker',
  data(){
    return {
      picked: ref(new Date())
    }
  }
}

</script>


<style scoped>

</style>
