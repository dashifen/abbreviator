<!--suppress HtmlFormInputWithoutLabel, JSUnresolvedVariable -->

<script>
import Axios from 'axios';
export default {
  name: "abbreviator",
  data() {
    return {
      abbreviations: []
    };
  },
  mounted() {
    const request = '?action=get-abbreviations';
    Axios.get(ajaxurl + request).then((response) => {
      for(let abbreviation in response.data) {
        if (response.data.hasOwnProperty(abbreviation)) {
          this.addRow(abbreviation, response.data[abbreviation]);
        }
      }

      this.addRow('', '');
    });
  },
  methods: {
    addRow(abbreviation, meaning) {
      this.abbreviations.push({
        'abbreviation': abbreviation,
        'meaning': meaning
      });

      this.$nextTick(() => {
        const abbrFields = Array.from(
            document.querySelectorAll('input[type=text][id^=abbr]')
        );

        abbrFields[abbrFields.length - 1].focus();
      });
    },

    removeRow(index) {
      this.abbreviations.splice(index, 1);
    },

    isLastAbbr(index) {
      return index === this.abbreviations.length - 1;
    }
  }
};
</script>

<template>
  <table>
    <thead>
    <tr>
      <th id="abbreviation">Abbreviations</th>
      <th id="meanings">Meanings</th>
      <th id="buttons">
        <span class="screen-reader-text">
          This column is for the buttons which add and remove rows
          within this table
        </span>
      </th>
    </tr>
    </thead>
    <tbody id="abbreviations" aria-live="polite">
    <tr v-for="(abbreviation, index) in abbreviations">
      <td headers="abbreviation">
        <label :for="'abbr-'+index" class="screen-reader-text">Enter Abbreviation</label>
        <input type="text" name="abbreviations[]" :id="'abbr-'+index" :key="index" v-model="abbreviation.abbreviation">
      </td>
      <td headers="meanings">
        <label :for="'meaning-'+index" class="screen-reader-text">Enter Meaning</label>
        <input type="text" name="meanings[]" :id="'meaning-'+index" :key="index" v-model="abbreviation.meaning">
      </td>
      <td headers="buttons">
        <button v-if="isLastAbbr(index)" @click.prevent="addRow" aria-controls="abbreviations" class="button button-secondary">Add</button>
        <button v-else @click.prevent="removeRow(index)" aria-controls="abbreviations" class="button button-secondary">Remove</button>
      </td>
    </tr>
    </tbody>
  </table>
</template>
