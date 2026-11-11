# Campus Gather — project report outline

Replace placeholders, explain in your own words and add screenshots from your running application.

1. **Cover:** assignment/module, project name, university, member names and student IDs, submission date.
2. **Problem:** event information is scattered across informal channels. Describe your actual campus experience; do not invent survey results.
3. **Objectives:** central event browsing, searching/filtering, and organiser CRUD.
4. **Users and scope:** visitors browse; authenticated organisers manage all records. No payment or registration feature.
5. **Technologies:** HTML structures pages, CSS styles them, JavaScript confirms deletes, PHP handles requests/sessions/validation, MySQL stores data, Apache serves the application.
6. **Architecture:** explain the browser → PHP → PDO → MySQL request and response using a real create-event example.
7. **Database:** describe admins (id, email, password_hash) and events (id, title, category, starts_at, venue, organiser, description, created_at). They are independent tables in this small shared-organiser version. There is no creator foreign key or ownership tracking.
8. **Implementation:** show each CRUD screen, identify the relevant PHP file and SQL statement, explain validation and authorisation.
9. **Design:** explain the ivory/green/lime palette, typography, scan-friendly date blocks, responsive layout and labelled inputs.
10. **Testing:** copy the README checklist, record actual outcomes, screenshots and fixes. Do not claim unperformed testing.
11. **Limitations and future work:** no registrations, attendance, event end times or per-organiser ownership. Potential future additions: registration records with event foreign keys, attendance check-in and reminders.
12. **Conclusion:** explain what the completed project achieves based on actual testing.
13. **References and acknowledgments:** official documentation and assistance used, following course rules.
