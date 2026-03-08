---
description: Custom Bike Posting Workflow (Website Update + Instagram Auto-Post)
---
When the user asks you to add a new custom bike to the website and post it to Instagram, follow these exact steps:

1. **Update Website Content**: Update the relevant HTML file (e.g., `service-repair.html`) to add the new custom bike details, image file paths, and pricing provided by the user.
2. **Generate Instagram Text**: Based on the details added to the website, write a short, engaging Instagram caption (around 150-300 characters) including relevant hashtags (e.g. #highlander #custombike #自転車屋).
3. **Trigger Webhook for Posting**: Send a POST request to the Make.com Webhook URL (which the user configures) using `curl`. 
   *Note: If you do not have the webhook URL saved yet, ask the user for it.*
   The JSON payload should include:
   - `title`: The title of the custom bike
   - `image_url`: The public URL of the image
   - `caption`: The generated Instagram text
4. **Commit and Push**: Run `git add .`, `git commit -m "Add new custom bike"`, and `git push` to save the website changes.
5. **Notify User**: Inform the user that the site is updated and the Instagram post has been sent via Make.com.
